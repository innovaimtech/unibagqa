<#
.SYNOPSIS
  Puente de Impresión Térmica Directa HTTP (Unibag Print Bridge - Puerto 8767)

.DESCRIPTION
  Este script levanta un micro-servidor HTTP local en el puerto 8767 (http://localhost:8767/)
  que permite a la aplicación web PHP (ejecutada en servidor local o en VPS remoto) enviar
  etiquetas industriales directamente a impresoras térmicas conectadas por USB a este equipo.

  Mecanismo técnico:
  1. Utiliza la API nativa de Windows (winspool.drv - RawPrinterHelper) mediante P/Invoke.
  2. Envía comandos de control crudos (ZPL, TSPL, EPL) sin pasar por drivers gráficos GDI,
     garantizando velocidad instantánea y fidelidad milimétrica en códigos de barra.
  3. Soporta impresoras Zebra, Barpos, Argox, TSC y compatibles.

  Endpoints HTTP:
  - GET  /status : Lista las impresoras instaladas en Windows y la impresora predeterminada.
  - POST /print  : Recibe JSON {"content": "^XA...^XZ", "printer": "...", "doc_name": "..."}
                   y lo imprime de inmediato.
#>
param(
  [string]$Listen = 'http://localhost:8767/',
  [string]$DefaultPrinter = ''
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

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

  public static void SendStringToPrinter(string printerName, string content, string docName) {
    IntPtr hPrinter;
    if (!OpenPrinter(printerName, out hPrinter, IntPtr.Zero)) {
      throw new Exception("No se pudo abrir la impresora: " + printerName);
    }
    try {
      var di = new DOC_INFO_1();
      di.pDocName = docName;
      di.pDataType = "RAW";
      if (!StartDocPrinter(hPrinter, 1, di)) {
        throw new Exception("StartDocPrinter falló.");
      }
      try {
        if (!StartPagePrinter(hPrinter)) {
          throw new Exception("StartPagePrinter falló.");
        }
        try {
          byte[] bytes = System.Text.Encoding.UTF8.GetBytes(content);
          IntPtr pUnmanagedBytes = Marshal.AllocCoTaskMem(bytes.Length);
          try {
            Marshal.Copy(bytes, 0, pUnmanagedBytes, bytes.Length);
            int written = 0;
            if (!WritePrinter(hPrinter, pUnmanagedBytes, bytes.Length, out written) || written != bytes.Length) {
              throw new Exception("WritePrinter falló.");
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

$listener = New-Object System.Net.HttpListener
$listener.Prefixes.Add($Listen)
if ($Listen.Contains('localhost:')) {
  $listener.Prefixes.Add($Listen.Replace('localhost:', '127.0.0.1:'))
}
$listener.Start()

Write-Host ("Print bridge running: {0}" -f $Listen)

try {
  while ($true) {
    $ctx = $listener.GetContext()
    $req = $ctx.Request
    $res = $ctx.Response

    # Habilitar CORS para permitir peticiones desde la web (ej. VPS 149.50.140.21 o localhost)
    $res.AddHeader('Access-Control-Allow-Origin', '*')
    $res.AddHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
    $res.AddHeader('Access-Control-Allow-Headers', 'Content-Type, Accept')

    if ($req.HttpMethod -eq 'OPTIONS') {
      $res.StatusCode = 200
      $res.OutputStream.Close()
      continue
    }

    $path = $req.Url.AbsolutePath
    if ($path -eq '/' -or $path -eq '/health') {
      $res.StatusCode = 200
      $payload = New-JsonResponse @{ ok = $true; service = 'Unibag Print Bridge'; status = 'running' }
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
      $sr = New-Object System.IO.StreamReader($req.InputStream, $req.ContentEncoding)
      $body = $sr.ReadToEnd()
      $sr.Close()
    } catch {
      $res.StatusCode = 400
      $payload = New-JsonResponse @{ ok = $false; error = 'Body inválido.' }
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
      $payload = New-JsonResponse @{ ok = $false; error = 'JSON inválido.' }
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

    $printer = ''
    if ($propNames -contains 'printer') { $printer = [string]$data.printer }
    if ([string]::IsNullOrWhiteSpace($printer)) { $printer = $DefaultPrinter }
    $language = 'zpl'
    if ($propNames -contains 'language') { $language = ([string]$data.language).ToLowerInvariant() }
    if ([string]::IsNullOrWhiteSpace($language)) { $language = 'zpl' }
    $content = ''
    if ($propNames -contains 'content') { $content = [string]$data.content }
    if ([string]::IsNullOrWhiteSpace($content) -and ($propNames -contains 'zpl')) { $content = [string]$data.zpl }
    $copies = 1
    if ($propNames -contains 'copies') { $copies = [int]$data.copies }
    if ($copies -le 0) { $copies = 1 }

    if ([string]::IsNullOrWhiteSpace($printer)) {
      $res.StatusCode = 422
      $payload = New-JsonResponse @{ ok = $false; error = 'Falta printer (nombre de impresora).'}
      $bytes = [System.Text.Encoding]::UTF8.GetBytes($payload)
      $res.ContentType = 'application/json; charset=utf-8'
      $res.OutputStream.Write($bytes, 0, $bytes.Length)
      $res.OutputStream.Close()
      continue
    }
    if ([string]::IsNullOrWhiteSpace($content)) {
      $res.StatusCode = 422
      $payload = New-JsonResponse @{ ok = $false; error = 'Falta content.'}
      $bytes = [System.Text.Encoding]::UTF8.GetBytes($payload)
      $res.ContentType = 'application/json; charset=utf-8'
      $res.OutputStream.Write($bytes, 0, $bytes.Length)
      $res.OutputStream.Close()
      continue
    }

    try {
      for ($i=0; $i -lt $copies; $i++) {
        [RawPrinterHelper]::SendStringToPrinter($printer, $content, ("UNIBAG_LABEL_" + $language.ToUpperInvariant()))
      }
      $res.StatusCode = 200
      $payload = New-JsonResponse @{ ok = $true; language = $language }
    } catch {
      $res.StatusCode = 502
      $payload = New-JsonResponse @{ ok = $false; error = $_.Exception.Message }
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
