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

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$script:stubWeight = $null
$script:stubUntil = $null
$script:serial = $null
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
  # Dividir por saltos de linea y tomar la ultima linea no vacia
  $lines = $raw -split "[\r\n]+" | Where-Object { -not [string]::IsNullOrWhiteSpace($_) }
  $target = if ($lines.Count -gt 0) { $lines[-1] } else { $raw }

  # Extraer numeros con signo opcional y decimales (. o ,)
  $m = [regex]::Match($target, '[-+]?\s*(\d+(?:[.,]\d+)?)')
  if (-not $m.Success) {
    return $null
  }
  $s = $m.Groups[1].Value.Replace(',', '.')
  try {
    $v = [double]::Parse($s, [System.Globalization.CultureInfo]::InvariantCulture)
    if ($v -lt 0) {
      return $null
    }
    return [Math]::Round($v, 3)
  } catch {
    return $null
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

  $p = [System.IO.Ports.Parity]::$Parity
  $s = [System.IO.Ports.StopBits]::$StopBits

  $sp = New-Object System.IO.Ports.SerialPort $targetPort, $BaudRate, $p, $DataBits, $s
  $sp.NewLine = "`r`n"
  $sp.ReadTimeout = $ReadTimeoutMs
  $sp.WriteTimeout = 800
  $sp.DtrEnable = $true
  $sp.RtsEnable = $true
  $sp.Open()
  $script:serial = $sp
  Write-Host ("[SERIAL] Puerto {0} abierto correctamente a {1} baudios (8-{2}-{3})." -f $targetPort, $BaudRate, $Parity[0], $StopBits) -ForegroundColor Green
  return $true
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
    if (-not [string]::IsNullOrWhiteSpace($RequestCommand)) {
      $script:serial.Write($RequestCommand)
    }

    Start-Sleep -Milliseconds 80

    if ($script:serial.BytesToRead -gt 0) {
      $raw = $script:serial.ReadExisting()
    } else {
      $raw = $script:serial.ReadLine()
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
    return @{
      ok = $false
      weight_kg = $null
      raw = $raw
      error = "Trama serial recibida pero sin peso numerico valido: '$raw'"
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
  if ($Mode -eq 'serial') {
    return Read-WeightKgSerial
  }
  return Read-WeightKgStub
}

$listener = New-Object System.Net.HttpListener
$listener.Prefixes.Add($Listen)
$listener.Start()

Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host "     PUENTE DE BALANZA RS-232 / USB (UNIBAG LP-7516)      " -ForegroundColor Cyan
Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host ("Servidor HTTP activo en: {0}" -f $Listen) -ForegroundColor Green
Write-Host ("Modo: {0} | Puerto COM configurado: {1} | Baudios: {2}" -f $Mode, $ComPort, $BaudRate) -ForegroundColor Yellow
$initialPorts = [System.IO.Ports.SerialPort]::GetPortNames()
if ($initialPorts.Count -eq 0) {
    Write-Host "AVISO: No se detecta ningun puerto COM conectado en Windows todavia." -ForegroundColor Magenta
    Write-Host "Conecta el cable RS-232 a USB y el puente lo detectara automaticamente al solicitar peso." -ForegroundColor Magenta
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
