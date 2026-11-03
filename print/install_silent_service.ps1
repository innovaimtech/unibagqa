<#
.SYNOPSIS
  Instalador / Desinstalador del Servicio Silencioso de Impresión Térmica Unibag (Puerto 8767)

.DESCRIPTION
  Configura print_bridge.ps1 para que se ejecute 100% oculto en segundo plano (sin ventanas
  negras de CMD ni PowerShell en la barra de tareas) y se inicie automáticamente al encender
  Windows en las estaciones de trabajo con impresoras térmicas USB (HPRT, Barpos, Zebra, etc.).
#>
param(
  [switch]$Uninstall
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Continue'

$bridgePath = Join-Path $PSScriptRoot 'print_bridge.ps1'
$vbsRunnerPath = Join-Path $PSScriptRoot 'run_print_bridge_hidden.vbs'
$startupDir = [Environment]::GetFolderPath('Startup')
$startupVbsPath = Join-Path $startupDir 'UnibagPrintBridge.vbs'
$taskName = 'UnibagThermalPrintBridge'

function Stop-RunningPrintBridge() {
  try {
    $conns = Get-NetTCPConnection -LocalPort 8767 -State Listen -ErrorAction SilentlyContinue
    if ($conns) {
      foreach ($c in $conns) {
        if ($c.OwningProcess -gt 4 -and $c.OwningProcess -ne $PID) {
          Stop-Process -Id $c.OwningProcess -Force -ErrorAction SilentlyContinue
        }
      }
      Start-Sleep -Milliseconds 500
    }
  } catch {}
}

if ($Uninstall) {
  Write-Host "Deteniendo servicio de impresion termica Unibag..." -ForegroundColor Yellow
  Stop-RunningPrintBridge
  if (Test-Path $startupVbsPath) {
    Remove-Item -Path $startupVbsPath -Force -ErrorAction SilentlyContinue
  }
  if (Test-Path $vbsRunnerPath) {
    Remove-Item -Path $vbsRunnerPath -Force -ErrorAction SilentlyContinue
  }
  try {
    Unregister-ScheduledTask -TaskName $taskName -Confirm:$false -ErrorAction SilentlyContinue
  } catch {}
  Write-Host "[OK] Servicio silencioso de impresora desinstalado correctamente." -ForegroundColor Green
  exit 0
}

if (-not (Test-Path $bridgePath)) {
  Write-Host "[ERROR] No se encontro print_bridge.ps1 en: $bridgePath" -ForegroundColor Red
  exit 1
}

Write-Host "==============================================================" -ForegroundColor Cyan
Write-Host "   INSTALACION SERVICIO SILENCIOSO IMPRESORA TERMICA UNIBAG   " -ForegroundColor Cyan
Write-Host "==============================================================" -ForegroundColor Cyan

Stop-RunningPrintBridge

# Crear lanzador VBScript 100% invisible (WindowStyle = 0 -> oculto sin parpadeo ni icono)
$escapedBridge = $bridgePath.Replace('"', '""')
$vbsContent = @"
Set WshShell = CreateObject("WScript.Shell")
cmd = "powershell.exe -NoProfile -NonInteractive -WindowStyle Hidden -ExecutionPolicy Bypass -File ""$escapedBridge"""
WshShell.Run cmd, 0, False
"@

[System.IO.File]::WriteAllText($vbsRunnerPath, $vbsContent, [System.Text.Encoding]::ASCII)
[System.IO.File]::WriteAllText($startupVbsPath, $vbsContent, [System.Text.Encoding]::ASCII)
Write-Host ("[OK] Inicio automatico registrado en: {0}" -f $startupVbsPath) -ForegroundColor Green

# Registrar tambien Tarea Programada de usuario si el sistema lo permite (con reinicio automatico ante fallos)
try {
  $action = New-ScheduledTaskAction -Execute 'wscript.exe' -Argument ("//B //NoLogo `"{0}`"" -f $vbsRunnerPath)
  $trigger = New-ScheduledTaskTrigger -AtLogOn
  $settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -ExecutionTimeLimit ([TimeSpan]::Zero) -RestartCount 3 -RestartInterval (New-TimeSpan -Minutes 1)
  Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $trigger -Settings $settings -Force -ErrorAction Stop | Out-Null
  Write-Host ("[OK] Tarea programada '{0}' registrada con auto-recuperacion." -f $taskName) -ForegroundColor Green
} catch {
  Write-Host "[INFO] Inicio automatico activo mediante carpeta Startup del usuario actual." -ForegroundColor DarkCyan
}

# Iniciar inmediatamente en segundo plano
Write-Host "Iniciando servicio silencioso en segundo plano (puerto 8767)..." -ForegroundColor Cyan
Start-Process -FilePath 'wscript.exe' -ArgumentList @('//B', '//NoLogo', "`"$vbsRunnerPath`"") -WindowStyle Hidden

$verified = $false
$printersDetected = @()
for ($attempt = 1; $attempt -le 12; $attempt++) {
  Start-Sleep -Milliseconds 400
  try {
    $resp = Invoke-RestMethod -Uri 'http://127.0.0.1:8767/printers' -Method Get -TimeoutSec 2 -ErrorAction Stop
    if ($null -ne $resp -and $resp.ok -eq $true) {
      $verified = $true
      if ($null -ne $resp.printers) {
        $printersDetected = @($resp.printers)
      }
      break
    }
  } catch {}
}

if ($verified) {
  Write-Host ""
  Write-Host "[EXITO] Servicio de impresion termica activo y oculto en 127.0.0.1:8767" -ForegroundColor Green
  if ($printersDetected.Count -gt 0) {
    Write-Host ("Impresoras detectadas en Windows ({0}):" -f $printersDetected.Count) -ForegroundColor Yellow
    foreach ($p in $printersDetected) {
      Write-Host ("  - {0}" -f $p) -ForegroundColor White
    }
  }
  Write-Host ""
  Write-Host "Ya puedes cerrar esta ventana. La impresora funcionara automaticamente en Local, Red y VPS." -ForegroundColor Cyan
  exit 0
} else {
  Write-Host "[ADVERTENCIA] El servicio fue registrado pero aun no responde en el puerto 8767. Verifica que el puerto no este bloqueado por antivirus." -ForegroundColor Yellow
  exit 1
}

