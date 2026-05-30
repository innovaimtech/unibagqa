param(
  [string]$Listen = 'http://localhost:8766/',
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

  public static void SendStringToPrinter(string printerName, string zpl, string docName) {
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
          byte[] bytes = System.Text.Encoding.UTF8.GetBytes(zpl);
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
$listener.Start()

Write-Host ("Print bridge running: {0}" -f $Listen)

try {
  while ($true) {
    $ctx = $listener.GetContext()
    $req = $ctx.Request
    $res = $ctx.Response

    $path = $req.Url.AbsolutePath
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

    $printer = ''
    if ($data.PSObject.Properties.Name -contains 'printer') { $printer = [string]$data.printer }
    if ([string]::IsNullOrWhiteSpace($printer)) { $printer = $DefaultPrinter }
    $zpl = ''
    if ($data.PSObject.Properties.Name -contains 'zpl') { $zpl = [string]$data.zpl }
    $copies = 1
    if ($data.PSObject.Properties.Name -contains 'copies') { $copies = [int]$data.copies }
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
    if ([string]::IsNullOrWhiteSpace($zpl)) {
      $res.StatusCode = 422
      $payload = New-JsonResponse @{ ok = $false; error = 'Falta zpl.'}
      $bytes = [System.Text.Encoding]::UTF8.GetBytes($payload)
      $res.ContentType = 'application/json; charset=utf-8'
      $res.OutputStream.Write($bytes, 0, $bytes.Length)
      $res.OutputStream.Close()
      continue
    }

    try {
      for ($i=0; $i -lt $copies; $i++) {
        [RawPrinterHelper]::SendStringToPrinter($printer, $zpl, "UNIBAG_LABEL")
      }
      $res.StatusCode = 200
      $payload = New-JsonResponse @{ ok = $true }
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

