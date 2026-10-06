@echo off
title Unibag Scale Bridge (Puerto 8765)
cd /d "%~dp0"
powershell -ExecutionPolicy Bypass -NoExit -File "%~dp0scale\scale_bridge.ps1" -Mode serial -ComPort AUTO
