<#
.SYNOPSIS
  Puente de Impresión Térmica Directa HTTP (Unibag Print Bridge - Puerto 8767)

.DESCRIPTION
  Micro-servidor HTTP local en el puerto 8767 (http://127.0.0.1:8767/) que permite
  al ERP Unibag (ejecutado en localhost, Red LAN o VPS remoto en la nube) enviar
  etiquetas industriales directamente al spooler USB de Windows sin cuadros de diálogo.

  Características:
  - Compatible con Chrome / Edge Private Network Access (Access-Control-Allow-Private-Network: true).
  - Envío RAW directo vía winspool.drv (ZPL en UTF-8, EPL/TSPL en Latin-1 single-byte).
  - Auto-detección inteligente de impresoras térmicas instaladas (HPRT, Barpos, Zebra, TSC, Argox, 4BARCODE).
  - Endpoints:
    * GET  /health   : Estado del servicio.
    * GET  /printers : Lista de impresoras instaladas en Windows e impresora predeterminada.
    * POST /print    : Impresión RAW inmediata {"printer":"...", "language":"epl|tspl|zpl", "copies":1, "content":"..."}.
#>
param(
  [string]$Listen = 'http://localhost:8767/',
  [string]$DefaultPrinter = ''
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

try {
  Add-Type -AssemblyName System.Drawing -ErrorAction SilentlyContinue
} catch {}

function New-JsonResponse([object]$obj) {
  return ($obj | ConvertTo-Json -Depth 6 -Compress)
}

Add-Type -TypeDefinition @"
using System;
using System.Runtime.InteropServices;

public class RawPrinterHelper {
  [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Unicode)]
  public class DOC_INFO_1 {
    [MarshalAs(UnmanagedType.LPWStr)] public string pDocName;
    [MarshalAs(UnmanagedType.LPWStr)] public string pOutputFile;
    [MarshalAs(UnmanagedType.LPWStr)] public string pDataType;
  }

  [DllImport("winspool.Drv", EntryPoint="OpenPrinterW", SetLastError=true, CharSet=CharSet.Unicode)]
  public static extern bool OpenPrinter(string szPrinter, out IntPtr hPrinter, IntPtr pd);

  [DllImport("winspool.Drv", SetLastError=true)]
  public static extern bool ClosePrinter(IntPtr hPrinter);

  [DllImport("winspool.Drv", EntryPoint="StartDocPrinterW", SetLastError=true, CharSet=CharSet.Unicode)]
  public static extern bool StartDocPrinter(IntPtr hPrinter, int level, [In] DOC_INFO_1 di);

  [DllImport("winspool.Drv", SetLastError=true)]
  public static extern bool EndDocPrinter(IntPtr hPrinter);

  [DllImport("winspool.Drv", SetLastError=true)]
  public static extern bool StartPagePrinter(IntPtr hPrinter);

  [DllImport("winspool.Drv", SetLastError=true)]
  public static extern bool EndPagePrinter(IntPtr hPrinter);

  [DllImport("winspool.Drv", SetLastError=true)]
  public static extern bool WritePrinter(IntPtr hPrinter, IntPtr pBytes, int dwCount, out int dwWritten);

  public static void SendBytesToPrinter(string printerName, byte[] bytes, string docName) {
    IntPtr hPrinter;
    if (!OpenPrinter(printerName, out hPrinter, IntPtr.Zero)) {
      throw new Exception("No se pudo abrir la impresora en Windows: " + printerName);
    }
    try {
      var di = new DOC_INFO_1();
      di.pDocName = docName;
      di.pDataType = "RAW";
      if (!StartDocPrinter(hPrinter, 1, di)) {
        throw new Exception("StartDocPrinter fallo para: " + printerName);
      }
      try {
        if (!StartPagePrinter(hPrinter)) {
          throw new Exception("StartPagePrinter fallo.");
        }
        try {
          IntPtr pUnmanagedBytes = Marshal.AllocCoTaskMem(bytes.Length);
          try {
            Marshal.Copy(bytes, 0, pUnmanagedBytes, bytes.Length);
            int written = 0;
            if (!WritePrinter(hPrinter, pUnmanagedBytes, bytes.Length, out written) || written != bytes.Length) {
              throw new Exception("WritePrinter fallo al enviar datos RAW.");
            }
          } finally {
            Marshal.FreeCoTaskMem(pUnmanagedBytes);
          }
        } finally {
          EndPagePrinter(hPrinter);
        }
      } finally {
        EndDocPrinter(hPrinter);
      }
    } finally {
      ClosePrinter(hPrinter);
    }
  }
}
"@

function Get-InstalledWindowsPrinters() {
  $list = New-Object System.Collections.Generic.List[string]
  try {
    foreach ($p in [System.Drawing.Printing.PrinterSettings]::InstalledPrinters) {
      if (-not [string]::IsNullOrWhiteSpace([string]$p)) {
        $list.Add([string]$p)
      }
    }
  } catch {}

  if ($list.Count -eq 0) {
    try {
      $cimPrinters = Get-CimInstance -ClassName Win32_Printer -ErrorAction SilentlyContinue
      foreach ($cp in $cimPrinters) {
        if ($null -ne $cp.Name -and -not [string]::IsNullOrWhiteSpace([string]$cp.Name)) {
          $list.Add([string]$cp.Name)
        }
      }
    } catch {}
  }
  return @($list)
}

function Get-DefaultWindowsPrinter() {
  try {
    $ps = New-Object System.Drawing.Printing.PrinterSettings
    if (-not [string]::IsNullOrWhiteSpace($ps.PrinterName)) {
      return [string]$ps.PrinterName
    }
  } catch {}
  return ''
}

function Test-IsVirtualPrinter([string]$name) {
  if ([string]::IsNullOrWhiteSpace($name)) { return $true }
  return ($name -match '(?i)(Microsoft Print to PDF|XPS Document Writer|OneNote|Fax|Send To)')
}

function Resolve-ThermalPrinterName([string]$requested, [string]$lang) {
  $installed = @(Get-InstalledWindowsPrinters)
  if ($installed.Count -eq 0) {
    return $requested
  }
  # Importante: cuando se pide un nombre concreto esta funcion devuelve $null si no existe.

  $reqTrim = if ($null -ne $requested) { $requested.Trim() } else { '' }
  $isGenericReq = ([string]::IsNullOrWhiteSpace($reqTrim) -or $reqTrim -ieq 'DEFAULT' -or $reqTrim -ieq 'AUTO')

  if (-not $isGenericReq) {
    # 1. Coincidencia exacta (case-insensitive)
    foreach ($p in $installed) {
      if ($p -ieq $reqTrim) { return $p }
    }
    # 2. Coincidencia parcial (ej. "HPRT HT100 - EPL" vs "HPRT HT100")
    foreach ($p in $installed) {
      if (-not (Test-IsVirtualPrinter $p)) {
        if ($p.IndexOf($reqTrim, [System.StringComparison]::OrdinalIgnoreCase) -ge 0 -or
            $reqTrim.IndexOf($p, [System.StringComparison]::OrdinalIgnoreCase) -ge 0) {
          return $p
        }
      }
    }
  }

  # 3. Coincidencia por marca según lenguaje o familia térmica industrial
  if ($lang -eq 'zpl') {
    foreach ($p in $installed) {
      if ($p -match '(?i)(Zebra|ZDesigner|ZPL)') { return $p }
    }
  } elseif ($lang -eq 'epl') {
    foreach ($p in $installed) {
      if ($p -match '(?i)(HPRT|HT100|HT300|EPL|Zebra|ZDesigner)') { return $p }
    }
  } elseif ($lang -eq 'tspl') {
    foreach ($p in $installed) {
      if ($p -match '(?i)(Barpos|TSC|4BARCODE|Argox|Godex|Xprinter|TSPL|HPRT)') { return $p }
    }
  }

  # Si se pidio una impresora CONCRETA y no se encontro (ni por nombre ni por marca del
  # lenguaje) NO se improvisa con otra: enviar ZPL a una Barpos (TSPL) o a una impresora
  # de oficina desperdicia etiquetas. Se devuelve $null y el llamador responde con error.
  if (-not $isGenericReq) {
    return $null
  }

  foreach ($p in $installed) {
    if ($p -match '(?i)(HPRT|HT100|HT300|Barpos|Zebra|ZDesigner|TSC|Argox|Godex|Bixolon|4BARCODE|Xprinter|Thermal|Label|EPL|ZPL|TSPL)') {
      return $p
    }
  }

  # 4. Impresora predeterminada de Windows (si no es PDF/XPS/OneNote/Fax)
  $winDef = Get-DefaultWindowsPrinter
  if (-not [string]::IsNullOrWhiteSpace($winDef) -and -not (Test-IsVirtualPrinter $winDef)) {
    return $winDef
  }

  # 5. Primera impresora física instalada
  foreach ($p in $installed) {
    if (-not (Test-IsVirtualPrinter $p)) {
      return $p
    }
  }

  return $reqTrim
}

# Liberar puerto 8767 si quedó una instancia previa colgada
try {
  $existingConns = Get-NetTCPConnection -LocalPort 8767 -State Listen -ErrorAction SilentlyContinue
  if ($existingConns) {
    foreach ($conn in $existingConns) {
      $oldPid = $conn.OwningProcess
      if ($oldPid -gt 4 -and $oldPid -ne $PID) {
        # Solo se cierra una instancia previa de PowerShell (el propio puente colgado);
        # jamas un proceso ajeno que casualmente use el puerto 8767.
        $oldProc = Get-Process -Id $oldPid -ErrorAction SilentlyContinue
        if ($null -ne $oldProc -and $oldProc.ProcessName -match '^(powershell|pwsh)$') {
          Stop-Process -Id $oldPid -Force -ErrorAction SilentlyContinue
        }
      }
    }
    Start-Sleep -Milliseconds 400
  }
} catch {}

# Registro de trabajos ya procesados (job_id -> impresora) para evitar duplicados.
$script:processedJobs = @{}

$listener = New-Object System.Net.HttpListener
$listener.Prefixes.Add($Listen)
if ($Listen.Contains('localhost:')) {
  $listener.Prefixes.Add($Listen.Replace('localhost:', '127.0.0.1:'))
}
$listener.Start()

Write-Host ("Unibag Print Bridge activo en: {0}" -f $Listen) -ForegroundColor Green

try {
  while ($true) {
    $ctx = $listener.GetContext()
    $req = $ctx.Request
    $res = $ctx.Response

    # CORS + Chrome/Edge Private Network Access (PNA) para permitir llamadas desde LAN o VPS remoto
    $res.AddHeader('Access-Control-Allow-Origin', '*')
    $res.AddHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
    $res.AddHeader('Access-Control-Allow-Headers', 'Content-Type, Accept, X-Requested-With')
    $res.AddHeader('Access-Control-Allow-Private-Network', 'true')
    $res.AddHeader('Access-Control-Max-Age', '86400')

    if ($req.HttpMethod -eq 'OPTIONS') {
      $res.StatusCode = 200
      $res.OutputStream.Close()
      continue
    }

    $path = $req.Url.AbsolutePath

    if ($path -eq '/' -or $path -eq '/health' -or $path -eq '/status' -or $path -eq '/printers') {
      $printersList = @(Get-InstalledWindowsPrinters)
      $defPrinter = Get-DefaultWindowsPrinter
      $res.StatusCode = 200
      $payload = New-JsonResponse @{
        ok = $true
        service = 'Unibag Print Bridge'
        status = 'running'
        printers = $printersList
        default_printer = $defPrinter
      }
      $bytes = [System.Text.Encoding]::UTF8.GetBytes($payload)
      $res.ContentType = 'application/json; charset=utf-8'
      $res.OutputStream.Write($bytes, 0, $bytes.Length)
      $res.OutputStream.Close()
      continue
    }

    if ($path -ne '/print') {
      $res.StatusCode = 404
      $payload = New-JsonResponse @{ ok = $false; error = 'Not found'; path = $path }
      $bytes = [System.Text.Encoding]::UTF8.GetBytes($payload)
      $res.ContentType = 'application/json; charset=utf-8'
      $res.OutputStream.Write($bytes, 0, $bytes.Length)
      $res.OutputStream.Close()
      continue
    }

    $body = ''
    try {
      $sr = New-Object System.IO.StreamReader($req.InputStream, [System.Text.Encoding]::UTF8)
      $body = $sr.ReadToEnd()
      $sr.Close()
    } catch {
      $res.StatusCode = 400
      $payload = New-JsonResponse @{ ok = $false; error = 'Body invalido.' }
      $bytes = [System.Text.Encoding]::UTF8.GetBytes($payload)
      $res.ContentType = 'application/json; charset=utf-8'
      $res.OutputStream.Write($bytes, 0, $bytes.Length)
      $res.OutputStream.Close()
      continue
    }

    $data = $null
    try {
      $data = $body | ConvertFrom-Json
    } catch {
      $res.StatusCode = 400
      $payload = New-JsonResponse @{ ok = $false; error = 'JSON invalido.' }
      $bytes = [System.Text.Encoding]::UTF8.GetBytes($payload)
      $res.ContentType = 'application/json; charset=utf-8'
      $res.OutputStream.Write($bytes, 0, $bytes.Length)
      $res.OutputStream.Close()
      continue
    }

    $propNames = @()
    if ($null -ne $data -and $null -ne $data.PSObject -and $null -ne $data.PSObject.Properties) {
      $propNames = @($data.PSObject.Properties | ForEach-Object { $_.Name })
    }

    $requestedPrinter = ''
    if ($propNames -contains 'printer') { $requestedPrinter = [string]$data.printer }
    if ([string]::IsNullOrWhiteSpace($requestedPrinter)) { $requestedPrinter = $DefaultPrinter }

    $language = 'epl'
    if ($propNames -contains 'language') { $language = ([string]$data.language).ToLowerInvariant() }
    if ([string]::IsNullOrWhiteSpace($language)) { $language = 'epl' }

    $content = ''
    if ($propNames -contains 'content') { $content = [string]$data.content }
    if ([string]::IsNullOrWhiteSpace($content) -and ($propNames -contains 'zpl')) { $content = [string]$data.zpl }

    $copies = 1
    if ($propNames -contains 'copies') { $copies = [int]$data.copies }
    if ($copies -le 0) { $copies = 1 }

    $jobId = ''
    if ($propNames -contains 'job_id') { $jobId = [string]$data.job_id }

    # Trabajo repetido (mismo job_id ya procesado): se responde OK sin volver a imprimir.
    if (-not [string]::IsNullOrWhiteSpace($jobId) -and $script:processedJobs.ContainsKey($jobId)) {
      $res.StatusCode = 200
      $payload = New-JsonResponse @{ ok = $true; duplicate = $true; printer = $script:processedJobs[$jobId] }
      $bytes = [System.Text.Encoding]::UTF8.GetBytes($payload)
      $res.ContentType = 'application/json; charset=utf-8'
      $res.OutputStream.Write($bytes, 0, $bytes.Length)
      $res.OutputStream.Close()
      continue
    }

    $resolvedPrinter = Resolve-ThermalPrinterName -requested $requestedPrinter -lang $language

    if ([string]::IsNullOrWhiteSpace($resolvedPrinter)) {
      $res.StatusCode = 422
      $installedNow = @(Get-InstalledWindowsPrinters) -join ', '
      $msg = if ([string]::IsNullOrWhiteSpace($requestedPrinter) -or $requestedPrinter -ieq 'DEFAULT' -or $requestedPrinter -ieq 'AUTO') {
        'No se encontro ninguna impresora termica instalada en Windows.'
      } else {
        "No se encontro la impresora '$requestedPrinter' en Windows. Instaladas: $installedNow"
      }
      $payload = New-JsonResponse @{ ok = $false; error = $msg }
      $bytes = [System.Text.Encoding]::UTF8.GetBytes($payload)
      $res.ContentType = 'application/json; charset=utf-8'
      $res.OutputStream.Write($bytes, 0, $bytes.Length)
      $res.OutputStream.Close()
      continue
    }

    if ([string]::IsNullOrWhiteSpace($content)) {
      $res.StatusCode = 422
      $payload = New-JsonResponse @{ ok = $false; error = 'Falta content (comandos de etiqueta).' }
      $bytes = [System.Text.Encoding]::UTF8.GetBytes($payload)
      $res.ContentType = 'application/json; charset=utf-8'
      $res.OutputStream.Write($bytes, 0, $bytes.Length)
      $res.OutputStream.Close()
      continue
    }

    try {
      $rawBytes = if ($language -eq 'zpl') {
        [System.Text.Encoding]::UTF8.GetBytes($content)
      } else {
        [System.Text.Encoding]::GetEncoding(28591).GetBytes($content)
      }

      $docTitle = "UNIBAG_LABEL_" + $language.ToUpperInvariant()

      # Todas las copias viajan en UN SOLO trabajo del spooler: o se encolan completas
      # o no se encola nada. Antes se enviaba un trabajo por copia y un fallo a mitad
      # dejaba copias impresas y el trabajo completo se reenviaba (etiquetas dobles).
      $allBytes = $rawBytes
      if ($copies -gt 1) {
        $ms = New-Object System.IO.MemoryStream
        for ($i = 0; $i -lt $copies; $i++) { $ms.Write($rawBytes, 0, $rawBytes.Length) }
        $allBytes = $ms.ToArray()
      }
      [RawPrinterHelper]::SendBytesToPrinter($resolvedPrinter, $allBytes, $docTitle)

      # Se recuerda el job_id (maximo 200) para ignorar reenvios del mismo trabajo.
      if (-not [string]::IsNullOrWhiteSpace($jobId)) {
        if ($script:processedJobs.Count -ge 200) { $script:processedJobs.Clear() }
        $script:processedJobs[$jobId] = $resolvedPrinter
      }
      $res.StatusCode = 200
      $payload = New-JsonResponse @{
        ok = $true
        language = $language
        printer = $resolvedPrinter
      }
    } catch {
      $res.StatusCode = 502
      $payload = New-JsonResponse @{
        ok = $false
        printer = $resolvedPrinter
        error = $_.Exception.Message
      }
    }

    $bytes = [System.Text.Encoding]::UTF8.GetBytes($payload)
    $res.ContentType = 'application/json; charset=utf-8'
    $res.OutputStream.Write($bytes, 0, $bytes.Length)
    $res.OutputStream.Close()
  }
} finally {
  try { $listener.Stop() } catch {}
  try { $listener.Close() } catch {}
}
