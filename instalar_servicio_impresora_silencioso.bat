@echo off
chcp 65001 >nul
title Instalador Servicio Silencioso Impresora Termica Unibag
echo.
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0print\install_silent_service.ps1"
echo.
pause

