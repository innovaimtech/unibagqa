<?php
declare(strict_types=1);

/**
 * =============================================================================
 * Puente de Compatibilidad Retroactiva de Producción (public/prodwrk.php)
 * =============================================================================
 *
 * Propósito:
 * En el sistema ERP original (1BIT LTDA), los operadores y pantallas de planta
 * accedían a la producción a través de enlaces del tipo:
 *   prodwrk.php?mid=1, prodwrk.php?mid=2&agid=123, prodwrk.php?mid=3, etc.
 *
 * Para garantizar que los marcadores en los navegadores de las terminales de planta,
 * accesos directos y enlaces antiguos continúen funcionando sin problemas, este
 * script intercepta los parámetros legacy 'mid' y 'agid' y realiza una redirección
 * HTTP 302 transparente hacia las nuevas rutas RESTful modernas de ProductionModule:
 *
 * - mid=2 con agid>0 -> /production/work-orders/{agid}/operate (Consola del operador)
 * - mid=2            -> /production/work-orders/active (OTs en curso)
 * - mid=1 o mid=5    -> /production/work-orders/new (Iniciar nueva OT agendada)
 * - mid=3            -> /production/work-orders/history (Historial de turnos y OTs)
 * - Cualquier otro   -> /production/machines (Panel de asignación de máquina y turno)
 */

$mid = isset($_GET['mid']) ? (int)$_GET['mid'] : 0;
$agid = isset($_GET['agid']) ? (int)$_GET['agid'] : 0;

// Caso 1: Operar una OT específica ya agendada
if ($mid === 2 && $agid > 0) {
    header('Location: /production/work-orders/' . $agid . '/operate');
    exit;
}

// Caso 2: Listado de OTs actualmente activas en planta
if ($mid === 2) {
    header('Location: /production/work-orders/active');
    exit;
}

// Caso 3: Apertura o inicio de nueva OT agendada
if ($mid === 1 || $mid === 5) {
    header('Location: /production/work-orders/new');
    exit;
}

// Caso 4: Historial de órdenes de trabajo cerradas
if ($mid === 3) {
    header('Location: /production/work-orders/history');
    exit;
}

// Por defecto: Selección de máquina y apertura de turno
header('Location: /production/machines');
exit;