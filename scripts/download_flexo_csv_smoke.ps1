$ErrorActionPreference = 'Stop'

$base = 'http://localhost:8000'
$month = if ($args.Count -gt 0) { $args[0] } else { '2026-09' }

$s = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$loginHtml = (Invoke-WebRequest -UseBasicParsing "$base/login" -WebSession $s).Content
$csrf = [regex]::Match($loginHtml, 'name="_csrf"\s+value="([^"]+)"').Groups[1].Value
if ([string]::IsNullOrWhiteSpace($csrf)) { throw 'No se pudo leer CSRF.' }

Invoke-WebRequest -UseBasicParsing "$base/login" -Method Post -WebSession $s -Body @{
  _csrf = $csrf
  user_login = 'demo'
  user_pass = 'demo'
  user_company_id = '1'
  erp_area = 'ERP'
  appmode = '0'
  user_planta_id = '0'
} | Out-Null

$outFile = Join-Path $env:TEMP ("planilla_flexo_{0}.csv" -f $month)
try {
  $resp = Invoke-WebRequest -UseBasicParsing "$base/bonificaciones/flexo/export?month=$month" -WebSession $s -MaximumRedirection 0
  Write-Output ("STATUS=" + $resp.StatusCode)
  $resp.Content | Set-Content -Path $outFile -Encoding utf8
} catch {
  if ($_.Exception.Response) {
    Write-Output ("HTTP=" + $_.Exception.Response.StatusCode.value__)
    try { Write-Output ("LOCATION=" + $_.Exception.Response.Headers['Location']) } catch {}
  }
  Write-Output "STATUS=ERROR"
  throw
}
Write-Output ("FILE=" + $outFile)
Write-Output ("SIZE=" + (Get-Item $outFile).Length)
Get-Content $outFile -TotalCount 5 | ForEach-Object { Write-Output $_ }
