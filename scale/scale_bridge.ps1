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

function Parse-WeightKg([string]$raw) {
  if ([string]::IsNullOrWhiteSpace($raw)) {
    return $null
  }
  # Dividir por saltos de linea e inspeccionar desde la ultima linea no vacia hacia atras
  $lines = @($raw -split "[\r\n]+" | Where-Object { -not [string]::IsNullOrWhiteSpace($_) })
  if ($lines.Count -eq 0) {
    return $null
  }

  for ($i = $lines.Count - 1; $i -ge 0; $i--) {
    $target = $lines[$i]
    # Extraer numeros con signo opcional y decimales (. o ,)
    $m = [regex]::Match($target, '[-+]?\s*(\d+(?:[.,]\d+)?)')
    if ($m.Success) {
      $s = $m.Groups[1].Value.Replace(',', '.')
      try {
        $v = [double]::Parse($s, [System.Globalization.CultureInfo]::InvariantCulture)
        if ($v -ge 0) {
          return [Math]::Round($v, 3)
        }
      } catch {}
    }
  }
  return $null
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

  $w = Parse-WeightKg $raw
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
        Write-Host ("Liberando puerto 8765 ocupado por proceso previo PID {0}..." -f $p) -ForegroundColor Yellow
        Stop-Process -Id $p -Force -ErrorAction SilentlyContinue
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
    $res.AddHeader('Access-Control-Allow-Headers', 'Content-Type')

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
