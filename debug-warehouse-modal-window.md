# Depuración: No abre ventana emergente de bodega

- **sessionId:** `warehouse-modal-window`
- **fecha:** `2026-08-13`
- **estado:** `[CLOSED] - A. Arreglado`
- **entorno:** `localhost:8000` (PHP dev server, XAMPP PHP 8.0)
- **módulo:** Panel ERP → sección `Nivel de ocupación de bodegas`
- **síntoma (actual vs esperado):**
  - Actual: hacer clic sobre una fila de bodega **no abre** ninguna ventana emergente.
  - Esperado: al hacer clic (o presionar Enter/Espacio con foco) debe abrir un modal con resumen + detalle de rollos, pallets y cajas de esa bodega.
- **pasos de reproducción:**
  1) Iniciar sesión en Panel ERP (`/`)
  2) Ir a la sección `Nivel de ocupación de bodegas`
  3) Hacer clic sobre cualquier fila de la tabla
  4) Resultado actual = nada
- **ventana de regresión:** después de integrar selector `warehouse_filter` + modales por bodega.

## 5 hipótesis falseables

1. **H1: HTML del modal no se renderiza en el DOM**
   - El `foreach ($warehouseDetailMap as ...)` que genera `<div class="erp-prod-modal" id="erp-modal-warehouse-123"...>` no corre (mapa vacío, filtro no aplicado, no hay método listBoxesByWarehouseCode o alguna excepción suave).
   - *Cómo falsificar:* inspeccionar HTML final y buscar `erp-modal-warehouse-` o `data-modal-target`.

2. **H2: No hay coincidencia entre el `data-modal-target` de la fila y el `id` real del modal**
   - La fila usa `erp-modal-warehouse-{code}` pero el modal genera otro ID (ej: `{id}` vs `{code}`), o el `code` no numérico se salta por `is_numeric`.
   - *Cómo falsificar:* comparar string por string el ID del modal y el valor del `data-modal-target`.

3. **H3: El script de apertura se ejecuta ANTES de que los modales/filas existan en el DOM**
   - El `script` inline corre antes de que el HTML de la tabla + modales sea parseado por el navegador (por ejemplo si el `</script>` está antes de renderizar `$body` con la tabla), o el DOM listo no está garantizado.
   - *Cómo falsificar:* revisar el orden del render en `unibagRenderProductionDashboardPage` (primero el body HTML, luego `<script>`, luego `render(...)`), o usar `DOMContentLoaded`.

4. **H4: El handler `click` nunca llega al `data-modal-target` porque la fila contiene elementos anidados que bloquean (o el `button.closest('a')` aborta falsamente)**
   - El evento `click` no burbujea correctamente, o `event.target.closest('a')` devuelve `true` cuando no debería.
   - *Cómo falsificar:* inspeccionar handlers en DevTools o agregar instrumentación JS con Debug Server.

5. **H5: El modal sí se abre pero queda oculto visualmente (z-index, `overflow:hidden` del padre, `position`/`backdrop` mal)**
   - El HTML cambia `modal.hidden = false` correctamente, pero el overlay no se ve por CSS.
   - *Cómo falsificar:* con DevTools inspeccionar si el elemento modal pasa de `hidden` a visible; revisar `z-index`, `position`, `.erp-prod-modal` en la hoja de estilos.

## Registro de evidencia

- [x] `pre-fix` logs y screenshots
- [x] análisis → hipótesis confirmada/rechazada
- [x] fix mínimo aplicado
- [x] `post-fix` validación
- [x] (A) confirmado OK / (B) persiste / (C) síntomas cambiaron / (D) abortar

### Análisis y determinación de hipótesis

- H1 (HTML no renderizado) → **RECHAZADA**. Se confirmó vía `browser_evaluate` que `warehouseModalsCount = 46` y `firstWarehouseRowTarget = erp-modal-warehouse-100` existían y coincidían.
- H2 (IDs no coinciden) → **RECHAZADA**. `targetModalExists = true` y IDs eran iguales.
- H3 (script corre ANTES del DOM de bodegas) → **CONFIRMADA**. `pre-fix` tests: `kpiTest.changed = true` pero `warehouseTest.changed = false`. El `<script>` se insertaba después de los modales KPI (línea ~973), ANTES de la tabla de ocupación y los modales de bodega. Por eso `querySelectorAll('[data-modal-target]')` no attachaba handlers sobre las filas/modales de bodega.
- H4 (bloqueo de evento / closest('a')) → **RECHAZADA**. En `pre-fix` el evento click era procesado pero el modal no cambiaba; root cause era falta de listener, no bloqueo.
- H5 (modal abierto pero invisible) → **RECHAZADA**. `targetModal.hidden` permanecía `true` tras clic; no era CSS.

### Fix mínimo aplicado

Archivo: `src/Http/ErpReportsModule.php`
1) Se extrajo el bloque `<script>` de la zona entre modales KPI y panel de lectura ejecutiva.
2) Se movió al **final** del `$body`, inmediatamente antes de `render($embeddedInErp ? 'ERP' : 'Dashboard Producción', $body);`.
3) Se envolvió la inicialización en `initDashboardPage()` con guarda de `document.readyState === "loading" -> DOMContentLoaded`; así el attach es robusto sin importar orden del HTML.

### Validación post-fix

- Sintaxis PHP OK:
  - `php -l src/Http/ErpReportsModule.php` → No syntax errors
  - `php -l src/ReceptionService.php` → No syntax errors
- Smoke HTTP:
  - `/` → 303 (protegida, sin crash)
  - `/?warehouse_filter=ALL&filter_type=period` → 303 (OK)
  - `/login` → 200
- Runtime en navegador (localhost:8000 Panel ERP):
  - `scriptMovedBottom = true`
  - `modalCount = 29, triggerCount = 29`
  - `warehouse` test: `before.hidden=true` → `after.hidden=false, overflow=hidden` → `changed:true`
  - `kpi` test: `before.hidden=true` → `after.hidden=false` → `changed:true`

### Cierre

Usuario confirma A ("ya abre") → caso cerrado.
No se requiere cleanup de código porque el fix se quedó como parte de producción (no hubo hooks/instrumentales residuales).
