# Unibag - Sistema Operativo de Producción y Trazabilidad (PHP 8.4 + MySQL)

Este repositorio contiene la plataforma integrada de **Producción en Planta**, **Trazabilidad**, **Recepción e Inventario de Bobinas** y **Control de Operarios** para Unibag.

---

## 🏛️ Arquitectura del Sistema

El sistema opera con dos conexiones de base de datos simultáneas gestionadas a través de [`src/Db.php`](file:///c:/Users/Axiliarmu/Desktop/unibag%20proyecto/src/Db.php):

1. **ERP (Producción y Planificación)**:
   - Conexión principal remota a la base del ERP (`unibag_unibag` en `149.50.129.154`).
   - Contiene la lógica operativa viva: máquinas (`equipo`), asignaciones de turno (`prod_worker_init`), órdenes de trabajo (`prod_header`, `prod_agenda`), bitácora de eventos (`prod_worker_ot_events`), autocontrol de calidad (`equipo_puntos_autocontrol`, `prod_worker_ot_autocontrol`) y operarios (`workers`, `user`).
2. **TRZ (Trazabilidad y Bodegas)**:
   - Base de datos local/específica de trazabilidad (`unibag_trazabilidad`).
   - Alberga el ciclo de vida unitario de bobinas (Roll IDs), pesajes de balanza física, traspasos de bodega (100/200/300/400) y tomas de inventario.

---

## 🚀 Módulos Principales

### 1. Producción en Planta (`/production/*`)
- **Gestión de Máquinas y Turnos** (`/production/machines`): Apertura de turno por operario en máquinas de confección, flexografía y serigrafía. Pausa automática de puestos previos (`win_status = 3`).
- **Apertura de OTs** (`/production/work-orders/new`): Carga de órdenes agendadas para la máquina activa con ficha técnica (medidas, clichés, colores, cliente, requerimiento).
- **Consola del Operador** (`/production/work-orders/{agId}/operate`):
  - **Apertura / Setup**: Registro de setup de rodillos, clichés y ajuste de medidas.
  - **Avance de Producción**: Registro incremental de metros lineales, metros máquina, kilos y unidades.
  - **Pausas y Mantenciones**: Justificación clasificada de paradas operativas y fallas mecánicas/eléctricas.
  - **Consumo de Materiales**: Registro de bobinas e insumos consumidos.
  - **Autocontrol de Calidad**: Checklist de verificación técnica por tipo de máquina.
- **Cierre con Validación de Supervisor**: Autorización con credenciales de usuario del grupo supervisor (rol 31 o admin) para finalizar la orden.
- **Monitoreo en Tiempo Real e Historial** (`/production/work-orders/active` y `/history`): Seguimiento de OTs en curso y auditoría de turnos cerrados.

### 2. Recepción de Bobinas e Inventario (`/reception`, `/inventory`, `/warehouses`)
- Alta de bobinas con lectura directa de balanza (puerto COM o bridge HTTP).
- Identificador único de bobina (`ROLL-XXXXX`).
- Traspasos entre bodegas y tomas de inventario físicas.

### 3. Reportes e Integración ERP (`/reports/*`)
- Informes de bonificación por operario (Flexografía, Serigrafía, CYS).
- Análisis de mermas y KPIs de rendimiento.

---

## ⚙️ Estructura de Directorios

```text
├── api/                  # Endpoints y puente para despliegues Serverless / Vercel
├── database/             # Esquemas SQL iniciales y migraciones versionadas
│   ├── migrations/       # Migraciones incrementales
│   └── schema.sql        # Esquema inicial TRZ
├── print/                # Scripts puente para impresoras térmicas de etiquetas (Zebra/Argox)
├── public/               # Raíz pública del servidor web
│   ├── index.php         # Router central HTTP y layout maestro
│   └── js/               # Librerías estáticas (Chart.js)
├── scale/                # Bridge PowerShell para lectura de balanza serie LP-7516
├── scripts/              # Utilidades de mantenimiento y sincronización
├── src/                  # Capa de lógica de negocio y servicios
│   ├── Db.php            # Factoría PDO con auto-reconexión y tolerancia a fallos
│   ├── Env.php           # Parser seguro de variables de entorno .env
│   ├── InventoryCountService.php # Servicio de tomas de inventario
│   ├── PrintService.php  # Servicio de generación y formateo de etiquetas ZPL/ESC
│   ├── ProductionService.php     # Núcleo de producción (máquinas, OTs, eventos, calidad)
│   ├── ReceptionService.php      # Núcleo de recepción, bobinas, stock y bonos
│   ├── RollReceptionService.php  # Subservicio de recepción de bobinas
│   ├── ScaleService.php  # Conector para balanzas físicas
│   └── Http/             # Controladores HTTP modulares
│       ├── ApiModule.php         # Rutas de API REST
│       ├── AuthModule.php        # Autenticación y control de sesiones
│       ├── ErpReportsModule.php  # Vistas de reportes y bonificaciones ERP
│       ├── InventoryModule.php   # Vistas de tomas y conteo de inventario
│       ├── ProductionModule.php  # Vistas y flujos operativos de producción
│       └── WarehousesModule.php  # Vistas de bodegas y transferencias
└── .env                  # Configuración local de entorno (no versionado)
```

---

## 🛠️ Puesta en Marcha Local

### Requisitos
- PHP 8.2 o superior (con extensiones `pdo`, `pdo_mysql`, `curl`, `mbstring`).
- Servidor MySQL local para TRZ y acceso de red al ERP remoto.

### Pasos
1. Configurar el archivo `.env`:
   ```ini
   APP_ENV=local
   ERP_DB_HOST=tu_host_erp
   ERP_DB_NAME=unibag_unibag
   ...
   TRZ_DB_HOST=127.0.0.1
   TRZ_DB_NAME=unibag_trazabilidad
   ...
   ```
2. Iniciar el servidor local de desarrollo:
   ```bash
   php -S localhost:8000 -t public
   ```
3. Abrir en el navegador:
   - `http://localhost:8000/production/machines`
