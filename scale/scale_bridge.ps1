param(
  [ValidateSet('stub','serial')]
  [string]$Mode = 'stub',
  [string]$ComPort = 'COM3',
  [int]$BaudRate = 9600,
  [ValidateSet('None','Odd','Even','Mark','Space')]
  [string]$Parity = 'None',
  [int]$DataBits = 8,
  [ValidateSet('One','Two','OnePointFive')]
  [string]$StopBits = 'One',
  [int]$ReadTimeoutMs = 1200,
  [string]$Listen = 'http://localhost:8765/',
  [string]$RequestCommand = ''
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$script:stubWeight = $null
$script:stubUntil = $null

function New-JsonResponse([object]$obj) {
  return ($obj | ConvertTo-Json -Depth 5 -Compress)
}

function Parse-WeightKg([string]$raw) {
  if ([string]::IsNullOrWhiteSpace($raw)) {
    return $null
  }
  $m = [regex]::Match($raw, '[-+]?\d+(?:[.,]\d+)?')
  if (-not $m.Success) {
    return $null
  }
  $s = $m.Value.Replace(',', '.')
  $v = 0.0
  if (-not [double]::TryParse($s, [ref]$v)) {
    return $null
  }
  if ($v -lt 0) {
    return $null
  }
  return [Math]::Round($v, 3)
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
  }
}

$serial = $null

function Ensure-SerialOpen() {
  if ($Mode -ne 'serial') { return }
  if ($script:serial -ne $null -and $script:serial.IsOpen) { return }

  $p = [System.IO.Ports.Parity]::$Parity
  $s = [System.IO.Ports.StopBits]::$StopBits

  $sp = New-Object System.IO.Ports.SerialPort $ComPort, $BaudRate, $p, $DataBits, $s
  $sp.NewLine = "`r`n"
  $sp.ReadTimeout = $ReadTimeoutMs
  $sp.WriteTimeout = 800
  $sp.DtrEnable = $true
  $sp.RtsEnable = $true
  $sp.Open()
  $script:serial = $sp
}

function Read-WeightKgSerial() {
  Ensure-SerialOpen

  $raw = ''
  try {
    if (-not [string]::IsNullOrWhiteSpace($RequestCommand)) {
      $script:serial.Write($RequestCommand)
    }

    Start-Sleep -Milliseconds 120

    if ($script:serial.BytesToRead -gt 0) {
      $raw = $script:serial.ReadExisting()
    } else {
      $raw = $script:serial.ReadLine()
    }
  } catch {
    return @{
      ok = $false
      weight_kg = $null
      raw = $raw
      error = $_.Exception.Message
      model = 'LP-7516'
      source = 'serial'
    }
  }

  $w = Parse-WeightKg $raw
  if ($w -eq $null) {
    return @{
      ok = $false
      weight_kg = $null
      raw = $raw
      error = 'No se pudo parsear el peso desde la lectura serial.'
      model = 'LP-7516'
      source = 'serial'
    }
  }

  return @{
    ok = $true
    weight_kg = $w
    raw = $raw
    error = $null
    model = 'LP-7516'
    source = 'serial'
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

Write-Host ("Scale bridge running: {0}  Mode={1}  Model=LP-7516" -f $Listen, $Mode)

try {
  while ($true) {
    $ctx = $listener.GetContext()
    $req = $ctx.Request
    $res = $ctx.Response

    $path = $req.Url.AbsolutePath
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
    } else {
      $res.StatusCode = 502
    }

    $res.ContentType = 'application/json; charset=utf-8'
    $res.OutputStream.Write($bytes, 0, $bytes.Length)
    $res.OutputStream.Close()
  }
} finally {
  try { $listener.Stop() } catch {}
  try { $listener.Close() } catch {}
  try { if ($serial -ne $null -and $serial.IsOpen) { $serial.Close() } } catch {}
}
