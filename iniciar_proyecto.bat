@echo off
rem ==============================================================================
rem INICIADOR PRINCIPAL DEL SISTEMA UNIBAG (Web ERP/TRZ + Hardware Silencioso)
rem ==============================================================================
rem - Verifica si el Servicio Silencioso de Impresión (Puerto 8767) ya está activo;
rem   si no lo está, lo inicia en segundo plano sin abrir ventanas molestas.
rem - La Balanza opera directamente desde el navegador por Web Serial API nativa
rem   (y levanta el puente 8765 en segundo plano como respaldo local opcional).
rem - Inicia el Servidor Web PHP en http://localhost:8000 y abre el navegador.
rem ==============================================================================

title Iniciar Sistema Unibag ERP y Trazabilidad
echo ========================================================
echo   Iniciando Sistema Unibag ERP ^& Trazabilidad
echo ========================================================
echo.

cd /d "%~dp0"

rem [1/3] Servicio de Impresión Térmica (puerto 8767) en segundo plano si no está activo
netstat -ano | findstr ":8767 " | findstr "LISTENING" >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo [1/3] Activando Spooler de Impresion Termica en segundo plano ^(puerto 8767^)...
    powershell.exe -NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -Command "Start-Process powershell.exe -WindowStyle Hidden -ArgumentList '-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File ""%~dp0print\print_bridge.ps1""'"
) else (
    echo [1/3] Spooler de Impresion Termica ya activo ^(puerto 8767^).
)

rem [2/3] Puente local opcional de Balanza (puerto 8765) oculto como respaldo a Web Serial
netstat -ano | findstr ":8765 " | findstr "LISTENING" >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo [2/3] Preparando respaldo local de Balanza ^(puerto 8765^)...
    powershell.exe -NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -Command "Start-Process powershell.exe -WindowStyle Hidden -ArgumentList '-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File ""%~dp0scale\scale_bridge.ps1"" -Mode serial -ComPort AUTO'"
) else (
    echo [2/3] Respaldo local de Balanza ya activo ^(puerto 8765^).
)

rem [3/3] Servidor Web Local PHP (puerto 8000)
netstat -ano | findstr ":8000 " | findstr "LISTENING" >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo [3/3] Iniciando Servidor Web PHP ^(http://localhost:8000^)...
    start "Unibag Web Server (Puerto 8000)" C:\xampp\php\php.exe -S 127.0.0.1:8000 router.php
) else (
    echo [3/3] Servidor Web PHP ya activo en puerto 8000.
)

echo.
echo ========================================================
echo   Sistema listo! Abriendo navegador en http://localhost:8000
echo ========================================================
timeout /t 2 >nul
start http://localhost:8000/reception
exit
