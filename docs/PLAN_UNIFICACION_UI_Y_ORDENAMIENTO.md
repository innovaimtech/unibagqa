# Auditoría de Modernización, Plan de Unificación Visual (UI/UX) y Ordenamiento General

## 1. Revisión del `PLAN_MODERNIZACION_Y_ARQUITECTURA_UNIBAG.md`: ¿Qué está listo y qué falta?

Tras auditar el estado del repositorio frente a [`PLAN_MODERNIZACION_Y_ARQUITECTURA_UNIBAG.md`](file:///c:/Users/Axiliarmu/Desktop/unibag%20proyecto/docs/PLAN_MODERNIZACION_Y_ARQUITECTURA_UNIBAG.md), este es el balance real:

| Eje del Plan Maestro | Estado Actual | Detalle Técnico Verificado |
| :--- | :---: | :--- |
| **Hardware Directo: Balanzas e Impresoras** *(Sección 4 / Fase 2)* | **✅ 100% Completado** | Web Serial API nativa en las 8 pantallas de pesaje ([`unibag-hardware.js`](file:///c:/Users/Axiliarmu/Desktop/unibag%20proyecto/public/assets/js/unibag-hardware.js)), servicio silencioso Windows puerto `8767` con PNA ([`install_silent_service.ps1`](file:///c:/Users/Axiliarmu/Desktop/unibag%20proyecto/print/install_silent_service.ps1)), WebUSB y TCP `9100` directo ([`PrintService.php`](file:///c:/Users/Axiliarmu/Desktop/unibag%20proyecto/src/PrintService.php)). |
| **Ruptura de los 3 Monolitos Principales** *(Sección 2.1)* | **✅ Completado** | [`index.php`](file:///c:/Users/Axiliarmu/Desktop/unibag%20proyecto/public/index.php) bajó de `26.042` → **`160` líneas**; `ProductionModule` se separó en `src/Http/Production/` + `src/Views/production/`; [`ProductionService.php`](file:///c:/Users/Axiliarmu/Desktop/unibag%20proyecto/src/ProductionService.php) (`80` líneas) y [`ReceptionService.php`](file:///c:/Users/Axiliarmu/Desktop/unibag%20proyecto/src/ReceptionService.php) (`217` líneas) quedaron como fachadas limpias. |
| **Sistema de Diseño Unificado (UI/UX)** *(Sección 3)* | **✅ 100% Completado** | Hojas corporativas [`unibag-ui.css`](file:///c:/Users/Axiliarmu/Desktop/unibag%20proyecto/public/assets/css/unibag-ui.css) y [`unibag-ui.js`](file:///c:/Users/Axiliarmu/Desktop/unibag%20proyecto/public/assets/js/unibag-ui.js), layout 100% fluido sin topes de `1100px`, contraste WCAG AA, cero letras invisibles y filtros elásticos. |
| **Ordenamiento de Raíz, CSS y Módulos HTTP Grandes** *(Sección 2.1–2.2)* | **✅ 100% Completado** | Raíz limpia (auditorías a `storage/audits/2026-10/`, docs a `docs/`, launchers obsoletos a `scripts/legacy-bat/`); modularización completa de `src/Http/Bonuses/` (6 módulos) y `src/Http/Reports/` (7 módulos) con cargador PSR-4 [`src/bootstrap.php`](file:///c:/Users/Axiliarmu/Desktop/unibag%20proyecto/src/bootstrap.php). |
| **Actualización de Motor PHP `8.0.30` → `PHP 8.4+` + `OPcache JIT`** *(Sección 1)* | **✅ Base Lista** | Código 100% compatible con tipado estricto PHP 8.0 a 8.4+, `composer.json` configurado. |
| **Migración opcional a Laravel 11/12 + Livewire** *(Sección 2.2 / Fases 3–4)* | **🧭 Decisión Estratégica** | Con la modularización y el nuevo Sistema de Diseño CSS unificado, el sistema PHP nativo queda ultra-rápido, limpio y modular sin dependencias pesadas de frameworks externos. |

---

## 2. Diagnóstico de Problemas Visuales Resueltos

### A. Ocupación de Pantalla Completa
1. **Eliminación del cuello de botella global de `1100px`:** [`erp_shell.php`](file:///c:/Users/Axiliarmu/Desktop/unibag%20proyecto/src/Views/layouts/erp_shell.php) ahora utiliza ancho fluido del 100% con padding lateral de `24px` alineado milimétricamente con la barra superior `.topbar` y `.subbar`.
2. **Remoción de topes dispares:** Se erradicaron los topes fijos `1340px`, `1400px`, `1440px !important`, `1600px` y tablas reducidas a `620px-960px`, logrando que todas las tablas, tarjetas de KPI y formularios ocupen el ancho completo del monitor sin márgenes muertos.

### B. Contraste y Filtros Corporativos Unificados
1. **Fin a las letras blancas invisibles en `<select>`:** Se forzó `select option, select optgroup { background-color: #FFFFFF !important; color: #0F172A !important; }` asegurando que en Windows Chrome/Edge el texto sea siempre negro de alto contraste sobre fondo blanco.
2. **Homologación de botones de acción:** Botones primarios turquesa `#00A9A6`, secundarios limpios `#FFFFFF` con borde `#CBD5E1` y texto `#1E293B`, y botones Excel verde esmeralda corporativo `#059669`.
3. **Sincronización `flatpickr` en `datepicker_assets.php`:** Los inputs clonados ahora heredan altura de `40px`, borde sutil y tipografía nítida idéntica a los selectores contiguos.
4. **Indicador activo en sub-menú:** Regla visual destacada para `.subitem.active` que permite al usuario saber en todo momento en qué vista o reporte se encuentra.

---

## 3. Arquitectura Modular Resultante

```text
src/
├── bootstrap.php                 # Autocargador PSR-4 y registro limpio de módulos
├── Http/
│   ├── Bonuses/                  # Dominio de Bonificaciones y Productividad
│   │   ├── BonusesRouter.php     # Enrutador principal /bonificaciones
│   │   ├── BonusConfigurationModule.php
│   │   ├── HelperBonusModule.php
│   │   ├── HelperBonusExportModule.php
│   │   ├── MaintenanceBonusModule.php
│   │   └── OpenActivityModule.php
│   ├── Reports/                  # Dominio de Informes y Dashboards ERP
│   │   ├── ReportsRouter.php     # Enrutador principal / y /reports/*
│   │   ├── DashboardAndGraphicsModule.php
│   │   ├── WasteAndInventoryReportsModule.php
│   │   ├── MachineReportsModule.php
│   │   ├── ServiceAndDispatchReportsModule.php
│   │   ├── ReceptionAuditModule.php
│   │   └── OperatorLunchModule.php
│   ├── Reception/                # Recepción Metrológica y Pesaje
│   ├── Production/               # Producción en Planta y Control de Turnos
│   └── ...                       # Bodegas, OTs, Trazabilidad, Mermas y Tintas
```

