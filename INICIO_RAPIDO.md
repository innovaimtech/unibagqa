# 🚀 INICIO RÁPIDO — Unibag

## ▶️ 1. Iniciar el servidor PHP local

### Opción A — Usando router.php (recomendado para rutas limpias)
```powershell
# Desde la raíz del proyecto:
cd "C:\Users\Axiliarmu\Desktop\unibag proyecto"
C:\xampp\php\php.exe -S localhost:8000 router.php
```
> ✅ **PHP detectado en este equipo:** `C:\xampp\php\php.exe` (PHP 8.0.30 — XAMPP)

### Opción B — Apuntando directo a /public
```powershell
cd "C:\Users\Axiliarmu\Desktop\unibag proyecto"
C:\xampp\php\php.exe -S localhost:8000 -t public
```

> 💡 Si `php` no está en el PATH, usa la ruta completa al ejecutable.
> Ejemplos comunes:
> ```powershell
> # XAMPP
> C:\xampp\php\php.exe -S localhost:8000 router.php
>
> # Laragon
> C:\laragon\bin\php\php-8.x.x\php.exe -S localhost:8000 router.php
>
> # PHP standalone instalado en C:\php
> C:\php\php.exe -S localhost:8000 router.php
> ```

---

## 🌐 2. URLs principales de la app

| Módulo                     | URL                                                  |
|----------------------------|------------------------------------------------------|
| Máquinas y Turnos          | http://localhost:8000/production/machines            |
| OTs Activas                | http://localhost:8000/production/work-orders/active  |
| Nueva OT                   | http://localhost:8000/production/work-orders/new     |
| Historial de OTs           | http://localhost:8000/production/work-orders/history |
| Recepción de Bobinas       | http://localhost:8000/reception                      |
| Inventario                 | http://localhost:8000/inventory                      |
| Bodegas                    | http://localhost:8000/warehouses                     |
| Reportes ERP               | http://localhost:8000/reports                        |
| Login / Autenticación      | http://localhost:8000/login                          |

---

## ⚙️ 3. Configuración del entorno (.env)

Archivo `.env` en la raíz del proyecto:

```ini
APP_ENV=local
APP_TIMEZONE=America/Santiago

# Base de datos ERP (remota)
ERP_DB_HOST=tu_host_erp
ERP_DB_PORT=3306
ERP_DB_NAME=unibag_unibag
ERP_DB_USER=tu_usuario_erp
ERP_DB_PASS=tu_clave_erp
ERP_DB_CHARSET=utf8mb4

# Base de datos TRZ (local)
TRZ_DB_HOST=127.0.0.1
TRZ_DB_PORT=3306
TRZ_DB_NAME=unibag_trazabilidad
TRZ_DB_USER=tu_usuario_trz
TRZ_DB_PASS=tu_clave_trz
TRZ_DB_CHARSET=utf8mb4

# Balanza
SCALE_MODE=stub                          # "stub" = sin balanza | "http" = balanza real
SCALE_HTTP_URL=http://localhost:8765/weight

# Impresion
PRINT_MODE=none                          # "none" | "http" | "direct"
PRINT_HTTP_URL=http://localhost:8767/print
PRINT_COPIES=1
```

---

## 🗄️ 4. Base de datos MySQL local (TRZ)

```powershell
# Iniciar servicio MySQL (si no corre automaticamente)
net start MySQL80
# o
net start MySQL

# Crear la base si no existe
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS unibag_trazabilidad CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Aplicar esquema inicial
mysql -u root -p unibag_trazabilidad < database\schema.sql
```

---

## 🛠️ 5. Scripts de mantenimiento

```powershell
# Sincronizar plan de produccion ERP
php scripts/sync_erp_production_plan.php

# Actualizar catalogo de telas
php scripts/upsert_tela_catalog.php

# Generar reporte mensual PPTX (requiere Python)
python scripts/generate_monthly_pptx.py
```

---

## 📁 6. Estructura clave

```
unibag proyecto/
├── .env                    <- Variables de entorno (NO subir a Git)
├── router.php              <- Router para servidor PHP built-in
├── public/
│   ├── index.php           <- Entry point + layout maestro
│   └── libs/config.php     <- Configuracion legacy
├── src/
│   ├── Db.php              <- Conexiones PDO (ERP + TRZ)
│   ├── Env.php             <- Parser de .env
│   ├── Http/               <- Controladores de modulos
│   │   ├── AuthModule.php
│   │   ├── ProductionModule.php
│   │   ├── WarehousesModule.php
│   │   ├── InventoryModule.php
│   │   ├── ErpReportsModule.php
│   │   └── ApiModule.php
│   ├── ProductionService.php
│   ├── ReceptionService.php
│   ├── ScaleService.php
│   └── PrintService.php
├── database/
│   ├── schema.sql          <- Esquema inicial TRZ
│   └── migrations/         <- Migraciones incrementales
└── scripts/                <- Utilidades de mantenimiento
```

---

## ✅ Checklist de inicio

- [ ] MySQL local corriendo con base `unibag_trazabilidad`
- [ ] Archivo `.env` configurado correctamente
- [ ] Red/VPN activa si se necesita acceso al ERP en `149.50.129.154`
- [ ] Servidor PHP corriendo: `php -S localhost:8000 router.php`
- [ ] Abrir: http://localhost:8000/production/machines
