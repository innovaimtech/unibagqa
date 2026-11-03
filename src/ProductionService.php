<?php

declare(strict_types=1);

require_once __DIR__ . '/MachineRollContinuityService.php';

/**
 * =============================================================================
 * Servicio de Producción · Planta, Máquinas, Operarios, OTs y Eventos
 * (src/ProductionService.php)
 * =============================================================================
 *
 * Este servicio recrea, moderniza y centraliza toda la lógica operativa que
 * residía en el sistema legacy (_BACKUP20260723: prodwrk.php y libs.prodwrk).
 *
 * Responsabilidades:
 * -----------------
 * 1. MÁQUINAS Y TURNOS:
 *    - Registro de apertura y cierre de turno en máquina (tabla `prod_worker_init`).
 *    - Pausa automática de máquinas previas al alternar de puesto (`win_status = 3`).
 *    - Asignaciones de turno planificadas (`turnos_config_assign`, `turnos`, `equipo`).
 *
 * 2. PROGRAMACIÓN Y ARRANQUE DE ÓRDENES DE TRABAJO (OTs):
 *    - Lectura de agenda programada por equipo y fecha (`prod_agenda`, `prod_header`, `orders`).
 *    - Inicio formal del trabajo por el operario (`prod_worker_ot` con `wok_status = 1`).
 *    - Recuperación del contexto completo técnico: medidas, clichés, colores, requerimiento.
 *
 * 3. BITÁCORA DE EVENTOS PRODUCTIVOS (`prod_worker_ot_events`):
 *    - Aperturas / Setup de máquina: cambio de medidas, clichés, rodillos (`evt_type = 'apertura'`).
 *    - Producción: registro de metros máquina, metros lineales, unidades y kilos (`evt_type = 'prod'`).
 *    - Pausas operativas: catalogadas por causas (`prod_pause_types`, `evt_type = 'pause'`).
 *    - Mantenciones mecánicas/eléctricas: catalogadas por fallas (`prod_repairtypes`, `evt_type = 'mantencion'`).
 *    - Consumos de insumos / bobinas: telas, tintas, adhesivos (`evt_type = 'materiales'`).
 *
 * 4. CALIDAD Y AUTOCONTROL (`prod_worker_ot_autocontrol`):
 *    - Lista de verificación por tipo de máquina (`equipo_puntos_autocontrol`).
 *    - Guardado de confirmación de puntos de control por el operario y supervisor.
 *
 * 5. CIERRE DE ORDEN DE TRABAJO:
 *    - Verificación estricta de credenciales de supervisor/administrador (rol 31 o tipo 1).
 *    - Cierre de la OT (`wok_status = 2`, `wok_enddat = timestamp`).
 *
 * 6. MONITOREO DE PLANTA:
 *    - Visualización en tiempo real de OTs en curso por equipo y operario.
 *    - Historial consolidado de órdenes terminadas con totales de producción y mermas.
 *
 * 7. PREPARADO PARA TRAZABILIDAD:
 *    - Métodos listos para recibir identificación de bobina hija/madre, pesaje de balanza y etiquetas.
 */
require_once __DIR__ . '/Services/Production/MachineShiftService.php';
require_once __DIR__ . '/Services/Production/ProductionClosureService.php';
require_once __DIR__ . '/Services/Production/WorkOrderAgendaService.php';
require_once __DIR__ . '/Services/Production/ProductionEventsService.php';
require_once __DIR__ . '/Services/Production/RewindAndPackagingService.php';
require_once __DIR__ . '/Services/Production/ProductionMaterialsService.php';
require_once __DIR__ . '/Services/Production/QualityAutocontrolService.php';

final class ProductionService
{
    use MachineShiftProductionTrait;
    use ProductionClosureServiceTrait;
    use WorkOrderAgendaServiceTrait;
    use ProductionEventsServiceTrait;
    use RewindAndPackagingServiceTrait;
    use ProductionMaterialsServiceTrait;
    use QualityAutocontrolServiceTrait;

    /** @var PDO Conexión a la base de datos principal de producción (ERP / unibagqa) */
    private PDO $erpPdo;

    /** @var PDO|null Conexión opcional a la base de datos local de trazabilidad (TRZ) */
    private ?PDO $trzPdo;

    public function __construct(PDO $erpPdo, ?PDO $trzPdo = null)
    {
        $this->erpPdo = $erpPdo;
        $this->trzPdo = $trzPdo;
    }

}
