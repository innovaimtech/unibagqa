<?php
/**
 * Plantilla de Vista · Pantalla de Asignación de Máquina Requerida (src/Views/production/need_machine.php)
 */
?>
<div style="background-color:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;padding:30px;text-align:center;max-width:600px;margin:30px auto">
    <div style="font-size:36px;color:#E1A500;margin-bottom:15px"><i class="fa fa-exclamation-triangle"></i></div>
    <div style="font-size:18px;font-weight:bold;color:#333;margin-bottom:10px">No tienes una máquina asignada actualmente</div>
    <div style="display:flex;justify-content:center;gap:12px;flex-wrap:wrap;margin-top:20px">
        <a href="/production/machines" class="btngreen" style="padding:10px 22px;font-size:13px;font-weight:bold;text-decoration:none;border-radius:4px;display:inline-flex;align-items:center;gap:6px">
            <i class="fa fa-arrow-right"></i> Ir a Asignación de Máquinas
        </a>
        <a href="/logout" class="btnred" style="padding:10px 18px;font-size:13px;font-weight:bold;text-decoration:none;background:#DC2626;color:#FFF;border-radius:4px;display:inline-flex;align-items:center;gap:6px" onclick="return confirm('¿Desea cerrar sesión del sistema?');" title="Cerrar sesión / Salir">
            <i class="fa fa-sign-out-alt"></i> Cerrar Sesión
        </a>
    </div>
</div>

