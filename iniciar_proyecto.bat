@echo off
rem ==============================================================================
rem INICIADOR PRINCIPAL DEL SISTEMA UNIBAG (Trazabilidad, Balanza e Impresión)
rem ==============================================================================
rem Este archivo levanta concurrentemente los tres servicios esenciales:
rem 1. Puente de Impresión Térmica USB (Puerto 8767 vía PowerShell)
rem 2. Puente de Balanza Industrial RS-232 / USB (Puerto 8765 vía PowerShell)
rem 3. Servidor Web Local PHP 8 (Puerto 8000 con XAMPP y router.php)
rem
rem Finalmente abre de forma automática el navegador en http://localhost:8000/reception.
rem ==============================================================================

title Iniciar Unibag Trazabilidad, Balanza y Puente de Impresion
echo ========================================================
echo   Iniciando Sistema Unibag Trazabilidad
echo ========================================================
echo.

rem Posicionarse en el directorio del script
cd /d "%~dp0"

rem [1/3] Levantar el puente de impresión térmica en segundo plano
echo [1/3] Iniciando Puente de Impresion (puerto 8767)...
start "Unibag Print Bridge (Puerto 8767)" powershell -ExecutionPolicy Bypass -NoExit -File "%~dp0print\print_bridge.ps1"

rem [2/3] Levantar el puente de lectura serie de balanza en segundo plano
echo [2/3] Iniciando Puente de Balanza RS-232/USB (puerto 8765)...
start "Unibag Scale Bridge (Puerto 8765)" powershell -ExecutionPolicy Bypass -NoExit -File "%~dp0scale\scale_bridge.ps1" -Mode serial -ComPort AUTO

rem [3/3] Levantar el servidor web local con el binario de PHP de XAMPP
echo [3/3] Iniciando Servidor Web PHP (puerto 8000)...
start "Unibag Web Server (Puerto 8000)" C:\xampp\php\php.exe -S 127.0.0.1:8000 router.php

echo.
echo ========================================================
echo   Servicios levantados con exito!
echo   - Web: http://localhost:8000
echo   - Puente Impresora: http://localhost:8767
echo   - Puente Balanza: http://localhost:8765
echo ========================================================
echo.

rem Esperar 3 segundos para que los puertos terminen de inicializar y abrir navegador
timeout /t 3 >nul
start http://localhost:8000/reception
exit

