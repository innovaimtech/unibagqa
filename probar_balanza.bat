@echo off
title Diagnostico de Balanza RS-232 / USB
cd /d "%~dp0"
powershell -ExecutionPolicy Bypass -NoProfile -File "%~dp0scale\test_scale_serial.ps1"
echo.
pause
