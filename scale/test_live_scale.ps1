param([int]$BaudRate = 9600)
Add-Type -Path (Join-Path $PSScriptRoot 'Win32Serial.cs')
$serial = New-Object Unibag.Serial.Win32Serial
try {
    if (-not $serial.Open('COM10', $BaudRate)) {
        Write-Host "No se pudo abrir COM10" -ForegroundColor Red
        return
    }
    Write-Host "==========================================================" -ForegroundColor Cyan
    Write-Host (" MONITOR EN VIVO DE BALANZA COM10 ({0} BAUDIOS)" -f $BaudRate) -ForegroundColor Cyan
    Write-Host " Presiona Ctrl+C para salir" -ForegroundColor Yellow
    Write-Host "==========================================================" -ForegroundColor Cyan
    Write-Host "Esperando bytes de la balanza... (presiona botones o coloca peso)" -ForegroundColor Gray
    
    $totalBytes = 0
    while ($true) {
        $raw = $serial.ReadBlock(400)
        if (-not [string]::IsNullOrEmpty($raw)) {
            $totalBytes += $raw.Length
            $hex = [System.BitConverter]::ToString([System.Text.Encoding]::ASCII.GetBytes($raw))
            Write-Host ("[{0}] RECIBIDOS {1} BYTES -> ASCII: '{2}' | HEX: {3}" -f (Get-Date -Format 'HH:mm:ss'), $raw.Length, $raw.Trim(), $hex) -ForegroundColor Green
        }
        Start-Sleep -Milliseconds 100
    }
} finally {
    $serial.Close()
    Write-Host "Puerto COM10 cerrado." -ForegroundColor DarkGray
}
