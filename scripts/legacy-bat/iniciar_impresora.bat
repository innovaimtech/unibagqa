@echo off
rem ==============================================================================
rem INICIADOR INDIVIDUAL · PUENTE DE IMPRESIÓN TÉRMICA (Puerto 8767)
rem ==============================================================================
rem Permite a la aplicación web (local o en la VPS) enviar comandos crudos (ZPL/TSPL)
rem a impresoras térmicas USB conectadas a esta máquina vía HTTP POST /print.
rem ==============================================================================

title Unibag Print Bridge - Impresora Termica (Puerto 8767)
echo ========================================================
echo   Iniciando Puente de Impresion Termica Unibag (Puerto 8767)
echo   Permite imprimir desde la VPS y Local a tu impresora USB
echo ========================================================
echo.
cd /d "%~dp0"
powershell -ExecutionPolicy Bypass -NoExit -File "%~dp0print\print_bridge.ps1"

