param(
  [string]$ComPort = '',
  [int]$BaudRate = 9600,
  [string]$Parity = 'None',
  [int]$DataBits = 8,
  [string]$StopBits = 'One',
  [int]$Seconds = 10
)

Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host "  DIAGNOSTICO Y PRUEBA DE BALANZA RS-232 / USB (UNIBAG)   " -ForegroundColor Cyan
Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host ""

$detectedPorts = [System.IO.Ports.SerialPort]::GetPortNames()

Write-Host "Puertos COM disponibles en Windows:" -ForegroundColor Yellow
if ($detectedPorts.Count -eq 0) {
    Write-Host "  [ATENCION] No se detecta ningun puerto COM conectado en Windows." -ForegroundColor Red
    Write-Host "  -> Conecta el cable USB del conversor RS-232 al computador." -ForegroundColor White
    Write-Host "  -> Si ya esta conectado, verifica en el Administrador de Dispositivos" -ForegroundColor White
    Write-Host "     si falta el driver (FTDI, CH340, Prolific, CP210x)." -ForegroundColor White
    Write-Host ""
    exit 1
}

foreach ($p in $detectedPorts) {
    Write-Host ("  - {0}" -f $p) -ForegroundColor Green
}

if ([string]::IsNullOrWhiteSpace($ComPort)) {
    $ComPort = $detectedPorts[0]
    Write-Host ("`nSeleccionando automaticamente el primer puerto: {0}" -f $ComPort) -ForegroundColor Cyan
}

Write-Host ("`nIntentando abrir puerto {0} a {1} baudios (8-N-1)..." -f $ComPort, $BaudRate) -ForegroundColor White

$sp = $null
try {
    $pEnum = [System.IO.Ports.Parity]::$Parity
    $sEnum = [System.IO.Ports.StopBits]::$StopBits
    $sp = New-Object System.IO.Ports.SerialPort $ComPort, $BaudRate, $pEnum, $DataBits, $sEnum
    $sp.ReadTimeout = 1500
    $sp.WriteTimeout = 800
    $sp.DtrEnable = $true
    $sp.RtsEnable = $true
    $sp.Open()
    Write-Host ("Puerto {0} abierto correctamente!" -f $ComPort) -ForegroundColor Green
    Write-Host ("Escuchando datos de la balanza durante {0} segundos..." -f $Seconds) -ForegroundColor Yellow
    Write-Host "Coloca peso en la balanza para verificar la lectura:`n" -ForegroundColor White

    $sw = [System.Diagnostics.Stopwatch]::StartNew()
    $receivedAny = $false

    while ($sw.Elapsed.TotalSeconds -lt $Seconds) {
        if ($sp.BytesToRead -gt 0) {
            $data = $sp.ReadExisting()
            if (-not [string]::IsNullOrEmpty($data)) {
                $receivedAny = $true
                $clean = $data.Replace("`r", "<CR>").Replace("`n", "<LF>`n")
                Write-Host -NoNewline $clean -ForegroundColor Cyan
            }
        }
        Start-Sleep -Milliseconds 100
    }

    Write-Host ""
    if ($receivedAny) {
        Write-Host "`n[EXITO] Se recibieron datos desde la balanza en $ComPort!" -ForegroundColor Green
    } else {
        Write-Host "`n[AVISO] No se recibieron caracteres durante $Seconds segundos." -ForegroundColor Yellow
        Write-Host "Revisa:" -ForegroundColor White
        Write-Host "  1. Que el indicador de la balanza este encendido y configurado en modo transmision continua o envio." -ForegroundColor White
        Write-Host "  2. Que la velocidad de baudios coincida (ej. 9600, 4800, 2400, 19200)." -ForegroundColor White
        Write-Host "  3. El cableado RX/TX de la balanza (Pin 2 y Pin 3 cruzados si es DB9)." -ForegroundColor White
    }
} catch {
    Write-Host ("Error abriendo o leyendo {0}: {1}" -f $ComPort, $_.Exception.Message) -ForegroundColor Red
} finally {
    if ($sp -ne $null -and $sp.IsOpen) {
        $sp.Close()
    }
}
