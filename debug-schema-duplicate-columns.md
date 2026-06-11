# Debug Session: schema-duplicate-columns [OPEN]

## Contexto
- Entorno: VPS Ubuntu 22.04 + Apache + MySQL 8
- Dominio: `innovaimtech.online`
- Sintoma principal: error fatal al iniciar la app por columnas duplicadas durante el bootstrap de esquema

## Sintomas observados
- `SQLSTATE[42S21]: Column already exists: 1060 Duplicate column name 'source_work_order_id'`
- `SQLSTATE[42S21]: Column already exists: 1060 Duplicate column name 'process_stage'`
- Mensaje funcional: `No hay conexion a la base de datos` y luego `Ruta no encontrada`
- Avisos secundarios: `Cannot modify header information - headers already sent`

## Hipotesis
1. `columnExists()` consulta el metadata de una conexion/base distinta y por eso devuelve falso aunque la columna ya exista.
2. `database/schema.sql` ya contiene columnas modernas y `ensureReceptionSchema()` intenta volver a agregarlas sin una verificacion compatible con MySQL 8 en el VPS.
3. La tabla `app_settings` no tiene correctamente guardado `reception_schema_version`, por lo que el bootstrap corre en cada request aunque la estructura ya este aplicada.
4. La tabla `rolls` fue cargada parcialmente con esquema nuevo y parcialmente con migraciones viejas, dejando un estado valido para uso pero incompatible con el bootstrap automatico actual.
5. El error de `Ruta no encontrada` es un efecto secundario del fatal temprano y no una falla real de routing.

## Evidencia actual
- `ReceptionService::__construct()` llama `ensureReceptionSchema()` cuando `reception_schema_version` no coincide con `reception_v5`.
- `ensureReceptionSchema()` intenta agregar `source_work_order_id` y `process_stage` solo si `columnExists('rolls', ...)` devuelve falso.
- Los logs de Apache muestran fatal exactamente en esos `ALTER TABLE`.
- `curl -I` sobre `/login`, `/`, `/production/shifts` y `/purchase-orders...` devuelve `404` con `Location: /logout`, pero esa prueba usa metodo `HEAD`, no `GET`.
- El router en `public/index.php` define rutas para `GET` y `POST`; no hay manejo explicito para `HEAD`, por lo que esa evidencia no prueba aun que las rutas GET reales fallen.
- Como la respuesta incluye `Set-Cookie` y `Location`, Apache si esta entregando la solicitud a PHP; el problema ya no parece ser de rewrite o virtual host.

## Siguiente paso
- Confirmar si `app_settings.reception_schema_version` existe y que valor tiene
- Probar rutas reales con `GET` usando `curl -i` o navegador en modo incognito
- Si `/login` en GET sigue redirigiendo a `/logout`, revisar el estado de sesion/permisos y el flujo de autenticacion
