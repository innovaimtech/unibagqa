<#
.SYNOPSIS
  Puente de Comunicación HTTP - Balanza Industrial RS-232 / USB (Unibag Scale Bridge)

.DESCRIPTION
  Este script levanta un micro-servidor HTTP local en el puerto 8765 (http://localhost:8765/)
  que permite a la aplicación web PHP (tanto en servidor local como desplegada en VPS)
  consultar el peso en tiempo real de la balanza física conectada al computador por USB/COM.

  Características principales:
  1. Detección automática del puerto COM activo (-ComPort AUTO) o manual (COM1, COM3, etc.).
  2. Protocolo de balanza industrial LP-7516 (9600 baudios, 8 bits, sin paridad, 1 bit de parada).
  3. Carga nativa de Win32Serial.cs para eludir limitaciones de la clase .NET SerialPort en
     adaptadores USB-Serie con chips CH340, FTDI y Prolific.
  4. Endpoints HTTP disponibles:
     - GET /weight : Retorna {"ok": true, "weight_kg": 15.420, "stable": true, ...}
     - GET /status : Diagnóstico del puerto serie, lecturas y configuración.
     - GET /ports  : Lista de puertos COM disponibles en Windows.

.PARAMETER Mode
  'serial' para conectar a la balanza física, o 'stub' para pruebas simuladas sin hardware.

.PARAMETER ComPort
  Puerto serie (ej. 'COM3') o 'AUTO' para escanear y conectar al primer puerto disponible.
#>
param(
  [ValidateSet('stub','serial')]
  [string]$Mode = 'serial',
  [string]$ComPort = 'AUTO',
  [int]$BaudRate = 9600,
  [ValidateSet('None','Odd','Even','Mark','Space')]
  [string]$Parity = 'None',
  [int]$DataBits = 8,
  [ValidateSet('One','Two','OnePointFive')]
  [string]$StopBits = 'One',
  [int]$ReadTimeoutMs = 1500,
  [string]$Listen = 'http://localhost:8765/',
  [string]$RequestCommand = ''
)

# StrictMode deshabilitado para evitar conflictos de propiedades dinámicas en hashtables
$ErrorActionPreference = 'Continue'

# Cargar helper nativo Win32Serial para evitar el bug de SetCommState / DTR en chips CH340 y Prolific
$csPath = Join-Path $PSScriptRoot 'Win32Serial.cs'
if (Test-Path $csPath) {
  try {
    Add-Type -Path $csPath -ErrorAction SilentlyContinue
  } catch {}
}

$script:stubWeight = $null
$script:stubUntil = $null
$script:serial = $null
$script:serialType = 'none'
$script:activeComPort = $ComPort
$script:lastRawReading = ''
$script:lastParsedWeight = $null

function New-JsonResponse([object]$obj) {
  return ($obj | ConvertTo-Json -Depth 5 -Compress)
}

function Resolve-ComPort() {
  if ($ComPort -ne 'AUTO' -and -not [string]::IsNullOrWhiteSpace($ComPort)) {
    return $ComPort
  }
  $ports = [System.IO.Ports.SerialPort]::GetPortNames()
  if ($ports.Count -gt 0) {
    return $ports[0]
  }
  return $null
}

# -----------------------------------------------------------------------------
# Interpreta la ULTIMA trama completa recibida de la balanza.
# Devuelve $null si la trama no es un peso valido, o un hashtable:
#   @{ weight = [decimal] kg (3 decimales); stable = [bool] }
# Reglas de seguridad (el peso invalido NUNCA debe llegar a una etiqueta):
#   - Solo se evalua la ultima linea: jamas se recurre a una lectura anterior.
#   - Sobrecarga (OL) o error (ERR) => invalido.
#   - Peso negativo => invalido (antes el signo se perdia y quedaba positivo).
#   - Unidades g / lb se convierten a kg; sin unidad se asume kg.
#   - Estable salvo que la trama diga US / UNSTABLE / MOTION / MOT.
# -----------------------------------------------------------------------------
function Parse-WeightKg([string]$raw) {
  if ([string]::IsNullOrWhiteSpace($raw)) {
    return $null
  }
  # Se separa por CR, LF y STX/ETX; solo cuenta la ultima trama completa.
  $lines = @($raw -split '[\r\n\x02\x03]+' | Where-Object { -not [string]::IsNullOrWhiteSpace($_) })
  if ($lines.Count -eq 0) {
    return $null
  }
  $line = $lines[$lines.Count - 1].Trim()

  # Si la lectura termino a mitad de trama (sin salto de linea final y sin unidad al
  # final) la ultima linea esta incompleta: se descarta para no leer "18.4" de "18.450".
  $terminated = ($raw -match '[\r\n\x03]\s*$')
  if (-not $terminated -and $line -notmatch '(?i)(kg|g|lb)$') {
    if ($lines.Count -lt 2) { return $null }
    $line = $lines[$lines.Count - 2].Trim()
  }

  # Sobrecarga o error de la balanza: lectura invalida.
  if ($line -match '(?i)\b(OL|OVERLOAD|ERR|ERROR)\b') {
    return $null
  }

  # Signo opcional, numero con punto o coma decimal y unidad opcional.
  $m = [regex]::Match($line, '(?i)([-+])?\s*(\d+(?:[.,]\d+)?)\s*(kg|g|lb)?\b')
  if (-not $m.Success) {
    return $null
  }
  # Peso negativo: invalido (nunca se convierte a positivo).
  if ($m.Groups[1].Value -eq '-') {
    return $null
  }

  try {
    $v = [decimal]::Parse($m.Groups[2].Value.Replace(',', '.'), [System.Globalization.CultureInfo]::InvariantCulture)
  } catch {
    return $null
  }
  switch ($m.Groups[3].Value.ToLowerInvariant()) {
    'g'  { $v = $v / 1000 }
    'lb' { $v = $v * [decimal]0.45359237 }
  }

  $unstable = ($line -match '(?i)\b(US|UNSTABLE|MOTION|MOT)\b')
  return @{
    weight = [Math]::Round($v, 3)
    stable = (-not $unstable)
  }
}

function Read-WeightKgStub() {
  if ($script:stubWeight -eq $null) { $script:stubWeight = 0.0 }
  if ($script:stubUntil -eq $null) { $script:stubUntil = [DateTime]::UtcNow.AddSeconds(0) }

  $now = [DateTime]::UtcNow
  if ($now -ge $script:stubUntil) {
    $pick = Get-Random -Minimum 1 -Maximum 10
    if ($pick -le 3) {
      $script:stubWeight = 0.0
      $script:stubUntil = $now.AddSeconds(20)
    } else {
      $script:stubWeight = [Math]::Round((Get-Random -Minimum 5.000 -Maximum 45.000), 3)
      $script:stubUntil = $now.AddSeconds(20)
    }
  }

  $noise = [Math]::Round((Get-Random -Minimum -0.020 -Maximum 0.020), 3)
  $w = [Math]::Round([Math]::Max(0.0, $script:stubWeight + $noise), 3)
  return @{
    ok = $true
    weight_kg = $w
    raw = $null
    error = $null
    model = 'LP-7516'
    source = 'stub'
    com_port = 'STUB'
  }
}

function Ensure-SerialOpen() {
  if ($Mode -ne 'serial') { return $true }
  if ($script:serial -ne $null -and $script:serial.IsOpen) { return $true }

  $targetPort = Resolve-ComPort
  if ([string]::IsNullOrWhiteSpace($targetPort)) {
    throw "No se encontro ningun puerto COM conectado en Windows. Conecta el cable USB del conversor RS-232."
  }
  $script:activeComPort = $targetPort

  # 1. Intentar con Win32Serial (diseñado para drivers CH340 sin DTR/RTS)
  try {
    $win32 = New-Object Unibag.Serial.Win32Serial
    if ($win32.Open($targetPort, $BaudRate)) {
      $script:serial = $win32
      $script:serialType = 'win32'
      Write-Host ("[SERIAL] Puerto {0} abierto correctamente vía Win32 API a {1} baudios." -f $targetPort, $BaudRate) -ForegroundColor Green
      return $true
    }
  } catch {
    Write-Host ("[SERIAL] Falló apertura Win32 ({0}), intentando fallback .NET..." -f $_.Exception.Message) -ForegroundColor Yellow
  }

  # 2. Fallback con System.IO.Ports.SerialPort
  try {
    $p = [System.IO.Ports.Parity]::$Parity
    $s = [System.IO.Ports.StopBits]::$StopBits
    $sp = New-Object System.IO.Ports.SerialPort $targetPort, $BaudRate, $p, $DataBits, $s
    $sp.NewLine = "`r`n"
    $sp.ReadTimeout = $ReadTimeoutMs
    $sp.WriteTimeout = 800
    $sp.Open()
    $script:serial = $sp
    $script:serialType = 'dotnet'
    Write-Host ("[SERIAL] Puerto {0} abierto vía .NET SerialPort a {1} baudios." -f $targetPort, $BaudRate) -ForegroundColor Green
    return $true
  } catch {
    throw $_.Exception.Message
  }
}

function Read-WeightKgSerial() {
  try {
    [void](Ensure-SerialOpen)
  } catch {
    return @{
      ok = $false
      weight_kg = $null
      raw = $null
      error = $_.Exception.Message
      model = 'LP-7516'
      source = 'serial'
      com_port = $script:activeComPort
    }
  }

  $raw = ''
  try {
    if ($script:serialType -eq 'win32') {
      if (-not [string]::IsNullOrWhiteSpace($RequestCommand)) {
        [void]$script:serial.SendCommand($RequestCommand)
        Start-Sleep -Milliseconds 40
      }
      # 1. Leer datos disponibles en cola del driver (0 ms si la balanza ya transmitió trama)
      $raw = $script:serial.ReadExisting()
      if ([string]::IsNullOrWhiteSpace($raw)) {
        # 2. Si no había trama en cola, esperar una fracción corta (máx 120ms en vez de 500ms)
        $raw = $script:serial.ReadBlock(120)
      }
      if ([string]::IsNullOrWhiteSpace($raw)) {
        # 3. Si no hay trama continua, solicitar activamente con comando 'R\r\n' (protocolo LP-7516)
        [void]$script:serial.SendCommand("R`r`n")
        Start-Sleep -Milliseconds 50
        $raw = $script:serial.ReadBlock(150)
      }
    } else {
      if (-not [string]::IsNullOrWhiteSpace($RequestCommand)) {
        $script:serial.Write($RequestCommand)
        Start-Sleep -Milliseconds 40
      }
      if ($script:serial.BytesToRead -gt 0) {
        $raw = $script:serial.ReadExisting()
      } else {
        $raw = $script:serial.ReadLine()
      }
    }
    $script:lastRawReading = $raw
  } catch {
    try { if ($script:serial -ne $null) { $script:serial.Close() } } catch {}
    $script:serial = $null
    return @{
      ok = $false
      weight_kg = $null
      raw = $raw
      error = $_.Exception.Message
      model = 'LP-7516'
      source = 'serial'
      com_port = $script:activeComPort
    }
  }

  $parsed = Parse-WeightKg $raw
  $w = if ($null -ne $parsed) { $parsed.weight } else { $null }
  if ($w -eq $null) {
    $msg = if ([string]::IsNullOrWhiteSpace($raw)) {
      "Puerto $script:activeComPort abierto pero sin datos transmitidos. En la balanza LP-7516 verifica: C18=4 (Envío continuo) y C19=3 (9600 baudios), o presiona PRINT si está en modo manual."
    } else {
      "Trama serial recibida pero sin peso numérico válido: '$raw'"
    }
    return @{
      ok = $false
      weight_kg = $null
      raw = $raw
      error = $msg
      model = 'LP-7516'
      source = 'serial'
      com_port = $script:activeComPort
    }
  }

  $script:lastParsedWeight = $w
  return @{
    ok = $true
    weight_kg = $w
    stable = $parsed.stable
    raw = $raw.Trim()
    error = $null
    model = 'LP-7516'
    source = 'serial'
    com_port = $script:activeComPort
  }
}

function Read-WeightKg() {
  $res = if ($Mode -eq 'serial') {
    Read-WeightKgSerial
  } else {
    Read-WeightKgStub
  }
  if ($res -is [System.Array]) {
    return $res[-1]
  }
  return $res
}

# Liberar puerto 8765 si otro proceso huérfano de PowerShell lo tiene ocupado
try {
  $existing = Get-NetTCPConnection -LocalPort 8765 -ErrorAction SilentlyContinue
  if ($existing) {
    foreach ($conn in $existing) {
      $p = $conn.OwningProcess
      if ($p -gt 4 -and $p -ne $PID) {
        # Solo se cierra una instancia previa de PowerShell; nunca un proceso ajeno.
        $oldProc = Get-Process -Id $p -ErrorAction SilentlyContinue
        if ($null -ne $oldProc -and $oldProc.ProcessName -match '^(powershell|pwsh)$') {
          Write-Host ("Liberando puerto 8765 ocupado por proceso previo PID {0}..." -f $p) -ForegroundColor Yellow
          Stop-Process -Id $p -Force -ErrorAction SilentlyContinue
        }
      }
    }
    Start-Sleep -Milliseconds 600
  }
} catch {}

$listener = New-Object System.Net.HttpListener
$listener.Prefixes.Add($Listen)
if ($Listen.Contains('localhost:')) {
  $listener.Prefixes.Add($Listen.Replace('localhost:', '127.0.0.1:'))
}
$listener.Start()

Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host "     PUENTE DE BALANZA RS-232 / USB (UNIBAG LP-7516)      " -ForegroundColor Cyan
Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host ("Servidor HTTP activo en: {0}" -f $Listen) -ForegroundColor Green
Write-Host ("Modo: {0} | Puerto COM: {1} | Baudios: {2}" -f $Mode, $ComPort, $BaudRate) -ForegroundColor Yellow
$initialPorts = [System.IO.Ports.SerialPort]::GetPortNames()
if ($initialPorts.Count -eq 0) {
    Write-Host "AVISO: No se detecta ningun puerto COM conectado en Windows todavia." -ForegroundColor Magenta
    Write-Host "Conecta el cable RS-232 a USB y el puente lo detectara automaticamente." -ForegroundColor Magenta
} else {
    Write-Host ("Puertos COM detectados: {0}" -f ($initialPorts -join ', ')) -ForegroundColor Green
}
Write-Host "Esperando peticiones desde el sistema de recepcion..." -ForegroundColor Gray
Write-Host "----------------------------------------------------------" -ForegroundColor DarkGray

try {
  while ($true) {
    $ctx = $listener.GetContext()
    $req = $ctx.Request
    $res = $ctx.Response

    # Habilitar CORS
    $res.AddHeader('Access-Control-Allow-Origin', '*')
    $res.AddHeader('Access-Control-Allow-Methods', 'GET, OPTIONS')
    $res.AddHeader('Access-Control-Allow-Headers', 'Content-Type, Accept, X-Requested-With')
    $res.AddHeader('Access-Control-Allow-Private-Network', 'true')
    $res.AddHeader('Access-Control-Max-Age', '86400')

    if ($req.HttpMethod -eq 'OPTIONS') {
      $res.StatusCode = 200
      $res.OutputStream.Close()
      continue
    }

    $path = $req.Url.AbsolutePath
    if ($path -eq '/health') {
      $isOpen = ($script:serial -ne $null -and $script:serial.IsOpen)
      $ports = [System.IO.Ports.SerialPort]::GetPortNames()
      $healthObj = @{
        ok = $true
        status = 'running'
        service = 'Unibag Scale Bridge'
        mode = $Mode
        configured_com = $ComPort
        active_com = $script:activeComPort
        serial_open = $isOpen
        detected_ports = $ports
        last_weight = $script:lastParsedWeight
      }
      $res.StatusCode = 200
      $payload = New-JsonResponse $healthObj
      $bytes = [System.Text.Encoding]::UTF8.GetBytes($payload)
      $res.ContentType = 'application/json; charset=utf-8'
      $res.OutputStream.Write($bytes, 0, $bytes.Length)
      $res.OutputStream.Close()
      continue
    }

    if ($path -ne '/weight') {
      $res.StatusCode = 404
      $payload = New-JsonResponse @{ ok = $false; error = 'Not found'; path = $path }
      $bytes = [System.Text.Encoding]::UTF8.GetBytes($payload)
      $res.ContentType = 'application/json; charset=utf-8'
      $res.OutputStream.Write($bytes, 0, $bytes.Length)
      $res.OutputStream.Close()
      continue
    }

    $result = Read-WeightKg
    $payload = New-JsonResponse $result
    $bytes = [System.Text.Encoding]::UTF8.GetBytes($payload)

    if ($result.ok -eq $true) {
      $res.StatusCode = 200
      Write-Host ("[{0}] Peso leido: {1} Kg (COM: {2})" -f (Get-Date -Format 'HH:mm:ss'), $result.weight_kg, $result.com_port) -ForegroundColor Green
    } else {
      $res.StatusCode = 200
      Write-Host ("[{0}] Esperando balanza: {1}" -f (Get-Date -Format 'HH:mm:ss'), $result.error) -ForegroundColor Yellow
    }

    $res.ContentType = 'application/json; charset=utf-8'
    $res.OutputStream.Write($bytes, 0, $bytes.Length)
    $res.OutputStream.Close()
  }
} finally {
  try { $listener.Stop() } catch {}
  try { $listener.Close() } catch {}
  try { if ($script:serial -ne $null -and $script:serial.IsOpen) { $script:serial.Close() } } catch {}
}
