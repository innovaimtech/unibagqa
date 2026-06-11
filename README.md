# Unibag - Sistema de Trazabilidad (PHP 8.4 + MySQL)

## Requisitos
- PHP 8.4
- MySQL 8.x (o compatible)

## Configuración
1. Copiar `.env.example` a `.env` y completar credenciales.
2. Crear la base de datos indicada en `DB_NAME`.
3. Ejecutar el script SQL:
   - `database/schema.sql`

## Ejecutar (desarrollo)
Desde la carpeta del proyecto:

- Servidor embebido de PHP:
  - `php -S localhost:8000 -t public`
- Abrir:
  - `http://localhost:8000`

## Seed Demo Completo
- Para poblar trazabilidad local, recepción ERP, producción ERP y sincronización de planificación en un solo paso:
  - `php scripts/seed_full_demo.php`
- El orquestador primero limpia demos anteriores y luego ejecuta en este orden:
  - `scripts/cleanup_demo_data.php`
  - `scripts/seed_demo_flow.php`
  - `scripts/seed_reception_demo.php`
  - `scripts/seed_erp_production_demo.php`
  - `scripts/sync_erp_production_plan.php`
- Requisito:
  - `.env` debe apuntar correctamente a la base local TRZ y a `unibagqa`

## Notas para XAMPP (Windows)
- Iniciar MySQL desde el panel de XAMPP (Start en MySQL).
- Si MySQL usa otro puerto, actualizar `DB_PORT` en `.env`.
- Si no tienes cliente mysql en PATH, puedes importar el esquema desde phpMyAdmin:
  - `http://localhost/phpmyadmin` → crear base → Importar → `database/schema.sql`

## Módulo disponible (MVP)
- Recepción de bobinas (Bodegas 100/200)
  - Alta de bobina con datos obligatorios
  - Generación de código único de bobina
  - Movimiento y stock por bodega

## Integración balanza (LP-7516)
- En recepción, el peso se captura desde balanza.
- Modo actual:
  - `SCALE_MODE=stub` devuelve un peso de prueba.
  - `SCALE_MODE=http` consulta `SCALE_HTTP_URL` y espera JSON `{ "weight_kg": 12.345 }`.
