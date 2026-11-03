# Informe de horas laborales: octubre de 2026

Fecha de verificacion: 29/10/2026.

## Alcance y fuente

Periodo del bono: 26/09/2026 al 25/10/2026, ambos incluidos.
Se interpreta "mes en curso" como octubre de 2026, no como el bono de noviembre que comienza el 26/10/2026.

La conexion `Db::erpPdo()` configurada actualmente devuelve la base
`unibagqa_v400`. Esta es la fuente efectivamente consultada; no se afirma
que los resultados provengan de `unibag_unibag`.

Se ejecutaron exclusivamente consultas de lectura sobre el ERP y su
metadata. No se accedio a TRZ ni se modificaron registros, conexiones
o estructura del ERP.

Las constantes 42 horas semanales y 5 dias por semana proceden de la
instruccion del solicitante. No son valores extraidos del ERP.
Este informe verifica la aritmetica solicitada, no constituye una
certificacion normativa ni aplica esa jornada a periodos historicos.

## 1. Horas programadas por dia

Operacion completa:

    42 horas/semana / 5 dias/semana = 8,4 horas/dia
    42 x 3.600 segundos / 5 = 30.240 segundos/dia
    30.240 segundos = 8 horas + 24 minutos = 8:24:00

Resultado exacto: **8,4 horas por dia, equivalentes a 8:24:00**.

Verificacion inversa:

    30.240 x 5 = 151.200 segundos = 42 horas
    8,4 x 5 = 42 horas

No se confunden 8,4 horas decimales con 8 horas y 40 minutos.
La aritmetica principal utiliza segundos enteros; no necesita redondeo.

## 2. Horas programadas mensuales

Sea D el valor validado de "DIAS DISPONIBLES (MES)".

    Horas totales/mes = 8,4 x D
    Segundos totales/mes = 30.240 x D

Resultado mensual: **PENDIENTE por falta de D validado en el ERP**.

Hallazgos:

- No se localizo un campo estructurado con el nombre de dias disponibles.
- `turnos_config` no contiene registros para el periodo consultado.
- `turnos_config_assign` tampoco contiene asignaciones para esas fechas.
- `calendar_appointments` esta vacia.
- `parametros` no devuelve entradas relacionadas con feriados,
  preventivo, colacion, disponibilidad, jornada o dias habiles.
- Existe la tabla `holidays`, pero solo contiene 01/05/2013 y 21/05/2013.
  No tiene cobertura de 2026 ni feriados registrados para este periodo.

El intervalo contiene 20 dias de lunes a viernes antes de descontar
feriados. Este conteo civil no es un valor de dias disponibles extraido
del ERP y no se utiliza como entrada del total mensual.

La ausencia de feriados de 2026 en un catalogo desactualizado no permite
concluir que hubo cero feriados. Tampoco se reutiliza el valor de 19 dias
obtenido anteriormente con una fuente externa, porque incumpliria el
alcance ERP exclusivo de esta solicitud.

## 3. Colacion mensual

La regla previa del solicitante establece 30 minutos por dia habil.
Si se valida D:

    Colacion mensual = 30 x D minutos
    Colacion mensual = 0,5 x D horas
    Colacion mensual = 1.800 x D segundos

Resultado: **PENDIENTE por falta de D validado**.

Esta es una regla proporcionada por el solicitante, no un parametro
mensual encontrado en el ERP. No se reemplaza por asistencia ni por
descansos efectivamente registrados.

## 4. Preventivo programado

Fuente legacy identificada: `prod_agenda_mantencion`, relacionada con
`equipo_manttype`.

La agenda contiene fecha, maquina, tipo y notas, pero no un campo de
duracion programada. El catalogo de tipos tampoco contiene horas.
El formulario legacy fija el timestamp de agenda a las 15:00 como
marcador de fecha: no representa una duracion.

Verificacion de datos:

- 17 agendas activas en total.
- Ninguna agenda activa en el periodo 26/09/2026 al 25/10/2026.
- No se encontraron notas o tipos con las expresiones de duracion
  revisadas: hora, minuto, duracion o numero seguido de h.

Resultado: **PENDIENTE; no hay horas preventivas programadas validables**.

No se equipara falta de agenda a cero horas sin una garantia de
integridad de la programacion. No se utiliza mantenimiento realizado,
cantidad de agendas, ni los valores locales sugeridos de 4 u 8 horas.

## 5. Tiempo disponible mensual

Sea P la suma validada de horas preventivas programadas.

    Disponible = Horas totales - Colacion mensual - Preventivo programado
    Disponible = (8,4 x D) - (0,5 x D) - P
    Disponible = 7,9 x D - P horas

Para operar sin redondeos, sea Ps la duracion preventiva en segundos:

    Disponible en segundos = (30.240 x D) - (1.800 x D) - Ps
    Disponible en segundos = 28.440 x D - Ps

Resultado: **PENDIENTE por falta de D y Ps validados**.

Control de coherencia requerido una vez disponibles las entradas:

    Disponible + Colacion + Preventivo = Horas totales

No se emite un tiempo disponible numerico ni un cumplimiento del bono
con entradas ausentes. Un resultado negativo requeriria revisar la
programacion y no se corregiria artificialmente a cero.

## 6. Trazabilidad de consultas

Conexion utilizada: `Db::erpPdo()`. Identificacion: `SELECT DATABASE()`.
Los limites timestamp se construyeron en `America/Santiago`:
inicio inclusivo 26/09/2026 00:00:00 y fin exclusivo 26/10/2026 00:00:00.

Consultas relevantes, con parametros de fecha:

```sql
SELECT holiday_date, holiday_desc
FROM holidays
WHERE holiday_date >= :inicio AND holiday_date < :fin;

SELECT a.id, a.ag_date, a.ag_equipo_id, t.mant_title, a.ag_notes
FROM prod_agenda_mantencion a
LEFT JOIN equipo_manttype t ON t.id = a.ag_equipo_mantid
WHERE a.ag_status > 0
  AND a.ag_date_stamp >= :inicio AND a.ag_date_stamp < :fin;

SELECT assign_year, assign_month, assign_day, COUNT(*) AS registros
FROM turnos_config_assign
WHERE (assign_year = 2026 AND assign_month = 9 AND assign_day >= 26)
   OR (assign_year = 2026 AND assign_month = 10 AND assign_day <= 25)
GROUP BY assign_year, assign_month, assign_day;
```

Tambien se inspeccionaron columnas con `SHOW COLUMNS` y
`information_schema.COLUMNS`, el contenido pertinente de `parametros`,
la cobertura de `holidays`, `calendar_appointments` y `turnos_config`,
y el formulario legacy que guarda la agenda preventiva.

## 7. Verificacion final

| Requisito | Resultado |
| --- | --- |
| 42 / 5 y equivalencia de duracion | VERIFICADO: 8,4 h = 8:24:00 |
| Prueba inversa en segundos enteros | VERIFICADO: 30.240 x 5 = 151.200 |
| Periodo 26 al 25 | VERIFICADO |
| Consultas solo al ERP, sin TRZ | CUMPLIDO |
| Sin modificaciones de datos ERP | CUMPLIDO |
| Fuente real identificada | VERIFICADO: unibagqa_v400 |
| Dias disponibles extraidos y completos | NO VERIFICABLE |
| Colacion mensual con entradas validadas | PENDIENTE |
| Preventivo programado con duracion | NO VERIFICABLE |
| Horas mensuales y tiempo neto definitivos | PENDIENTES |
| Valores faltantes no sustituidos por cero | CUMPLIDO |
| Formulas dimensionalmente coherentes | VERIFICADO |

Conclusion: la formula diaria esta comprobada y las formulas mensuales
estan documentadas. No se pueden certificar todos los requisitos de
calculo final con la informacion disponible en el ERP conectado.
Hace falta confirmar la base ERP objetivo y disponer en ella de un
calendario completo o del valor mensual validado de dias disponibles,
ademas de duraciones preventivas programadas por maquina.

El modulo y el Excel existentes no se han modificado en esta revision
y no deben interpretarse como adaptados a la nueva restriccion ERP
exclusiva.
