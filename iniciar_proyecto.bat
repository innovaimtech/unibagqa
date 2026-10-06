@echo off
title Iniciar Unibag Trazabilidad, Balanza y Puente de Impresion
echo ========================================================
echo   Iniciando Sistema Unibag Trazabilidad
echo ========================================================
echo.

cd /d "%~dp0"

echo [1/3] Iniciando Puente de Impresion (puerto 8767)...
start "Unibag Print Bridge (Puerto 8767)" powershell -ExecutionPolicy Bypass -NoExit -File "%~dp0print\print_bridge.ps1"

echo [2/3] Iniciando Puente de Balanza RS-232/USB (puerto 8765)...
start "Unibag Scale Bridge (Puerto 8765)" powershell -ExecutionPolicy Bypass -NoExit -File "%~dp0scale\scale_bridge.ps1" -Mode serial -ComPort AUTO

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
timeout /t 3 >nul
start http://localhost:8000/reception
exit
