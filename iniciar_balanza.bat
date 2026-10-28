@echo off
rem ==============================================================================
rem INICIADOR INDIVIDUAL · PUENTE DE BALANZA INDUSTRIAL (Puerto 8765)
rem ==============================================================================
rem Permite leer el peso de la balanza física conectada al puerto USB/COM
rem y exponerlo como una API HTTP local en http://localhost:8765/weight.
rem ==============================================================================

title Unibag Scale Bridge (Puerto 8765)
cd /d "%~dp0"
powershell -ExecutionPolicy Bypass -NoExit -File "%~dp0scale\scale_bridge.ps1" -Mode serial -ComPort AUTO

