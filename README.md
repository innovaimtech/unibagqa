# Unibag · Plataforma ERP, Producción en Planta y Trazabilidad

Plataforma corporativa integrada de **Unibag Chile** para **Gestión ERP e Informes Ejecutivos**, **Producción en Planta**, **Recepción Metrológica con Balanza Digital e Impresión Térmica**, **Bodegas e Inventario** y **Bonificaciones**.

---

## 📂 Estructura Ordenada del Proyecto

```text
├── data/                         # Plantillas base de reportes ejecutivos (PPTX mensual)
├── database/                     # Esquema SQL base y migraciones incrementales
├── deploy/                       # Configuraciones de despliegue VPS / Nginx
├── docs/                         # Documentación técnica, manuales e informes del proyecto
│   ├── INICIO_RAPIDO.md
│   ├── ACTUALIZAR_VPS.md
│   ├── INFORME_IMPLEMENTACION_IMPRESORAS_Y_BALANZAS.md
│   ├── PLAN_MODERNIZACION_Y_ARQUITECTURA_UNIBAG.md
│   ├── PLAN_MODULARIZACION_Y_SEPARACION_PHP.md
│   └── Informe_horas_laborales_ERP_octubre_2026.md
├── print/                        # Spooler silencioso Windows (puerto 8767 + PNA) para impresoras térmicas
├── public/                       # Raíz pública web
│   ├── index.php                 # Front Controller limpio (~160 líneas)
│   └── assets/
│       ├── css/unibag-ui.css     # Sistema de Diseño Corporativo Unificado (100% pantalla + contraste WCAG AA)
│       └── js/
│           ├── unibag-hardware.js# Motor Web Serial (Balanza nativa) + Impresión Térmica (Spooler/WebUSB)
│           └── unibag-ui.js      # Comportamiento UI compartido de filtros y controles
├── scale/                        # Puente opcional de respaldo serie RS-232/USB (puerto 8765 + PNA)
├── scripts/                      # Utilidades CLI de sincronización, PPTX y scripts archivados (legacy-bat/)
├── src/                          # Código fuente modularizado
│   ├── bootstrap.php             # Autoloader PSR-4 / mapa de clases y cargador ordenado de módulos
│   ├── Db.php                    # Conexiones PDO duales (ERP unibag_unibag + TRZ unibag_trazabilidad)
│   ├── PrintService.php          # Generación EPL/TSPL/ZPL, TCP 9100 directo y cola cliente remota
│   ├── ScaleService.php          # Integración de pesaje sin bloqueo para clientes locales y remotos
│   ├── ProductionService.php     # Fachada hacia servicios de dominio en src/Services/Production/
│   ├── ReceptionService.php      # Fachada hacia servicios de dominio en src/Services/Reception/
│   ├── Services/                 # Servicios de dominio desacoplados (Production/ y Reception/)
│   ├── Http/                     # Módulos y routers HTTP organizados por dominio funcional
│   │   ├── Bonuses/              # Bonificaciones ERP (Router, Flexo, Seri, Mantención, Ayudantes)
│   │   ├── Reports/              # Informes ERP (Router, Dashboards, Merma, Máquinas, OTIF, Auditoría)
│   │   ├── Reception/            # Enrutador y pantallas de Recepción metrológica
│   │   ├── Production/           # Enrutador y pantallas de Producción en Planta
│   │   ├── WorkOrders/           # Órdenes de Trabajo ERP y Aprobación de Setup
│   │   ├── Waste/                # Gestión de Mermas
│   │   └── Chemicals/            # Pesaje y Control de Tintas
│   └── Views/                    # Layouts globales (layouts/) y vistas desacopladas (production/, reception/)
├── storage/                      # Sesiones activas (sessions/), calendarios y respaldos (audits/)
├── composer.json                 # Configuración PSR-4 y herramientas de calidad (PHPStan / PHPUnit)
├── iniciar_proyecto.bat          # Iniciador local 1-clic (Web + servicios en segundo plano)
├── instalar_servicio_impresora_silencioso.bat # Instalador permanente sin ventanas para estaciones con impresora USB
└── router.php                    # Router estático/dinámico para servidor local PHP
```

---

## 🚀 Inicio Rápido y Hardware (Balanzas e Impresoras)

1. **Estaciones con Balanza Digital (RS-232 / USB-Serial):**
   - Funciona **sin instalar programas ni abrir consolas** en Chrome o Edge (tanto en local como conectado al servidor remoto/VPS).
   - Al entrar a cualquier pantalla de pesaje, haz clic una sola vez en el botón flotante **`⚖️ Balanza`** (abajo a la derecha) → **Conectar Puerto COM**. El navegador recordará el puerto automáticamente.
2. **Estaciones con Impresora Térmica USB (`HPRT` / `Barpos` / `Zebra`):**
   - Ejecuta **una sola vez** [`instalar_servicio_impresora_silencioso.bat`](file:///c:/Users/Axiliarmu/Desktop/unibag%20proyecto/instalar_servicio_impresora_silencioso.bat). Quedará registrado en Windows iniciando en segundo plano con 0 ventanas visibles y compatible con el servidor en la nube/LAN (Private Network Access).
   - Para impresoras conectadas por cable de red Ethernet, basta configurar `PRINT_MODE=tcp` y `PRINT_TCP_HOST=<IP_IMPRESORA>` en `.env` (impresión directa al puerto `9100` sin usar PC puente).
3. **Ejecución de Desarrollo Local:**
   - Doble clic en [`iniciar_proyecto.bat`](file:///c:/Users/Axiliarmu/Desktop/unibag%20proyecto/iniciar_proyecto.bat) o ejecutar en terminal:
     ```powershell
     C:\xampp\php\php.exe -S 127.0.0.1:8000 router.php
     ```

---

## 📚 Índice de Documentación (`docs/`)

- [Guía de Inicio Rápido](file:///c:/Users/Axiliarmu/Desktop/unibag%20proyecto/docs/INICIO_RAPIDO.md)
- [Guía de Actualización en VPS](file:///c:/Users/Axiliarmu/Desktop/unibag%20proyecto/docs/ACTUALIZAR_VPS.md)
- [Plan de Unificación Visual (UI/UX) y Ordenamiento](file:///c:/Users/Axiliarmu/Desktop/unibag%20proyecto/docs/PLAN_UNIFICACION_UI_Y_ORDENAMIENTO.md)
- [Informe de Implementación de Impresoras Térmicas y Balanzas](file:///c:/Users/Axiliarmu/Desktop/unibag%20proyecto/docs/INFORME_IMPLEMENTACION_IMPRESORAS_Y_BALANZAS.md)
- [Plan Maestro de Modernización y Arquitectura](file:///c:/Users/Axiliarmu/Desktop/unibag%20proyecto/docs/PLAN_MODERNIZACION_Y_ARQUITECTURA_UNIBAG.md)
- [Plan de Modularización y Separación PHP](file:///c:/Users/Axiliarmu/Desktop/unibag%20proyecto/docs/PLAN_MODULARIZACION_Y_SEPARACION_PHP.md)
