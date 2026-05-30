# Puente de balanza (LP-7516)

Este puente expone un endpoint HTTP local para que el sistema PHP lea el peso sin depender del driver directo en el navegador.

## Endpoint
- `GET http://localhost:8765/weight`
- Respuesta JSON:
  - `ok`: boolean
  - `weight_kg`: number (cuando ok=true)
  - `raw`: string | null (lectura cruda si aplica)
  - `error`: string | null
  - `model`: "LP-7516"
  - `source`: "stub" | "serial"

## Ejecutar (modo prueba)
En PowerShell:

```powershell
cd "c:\Users\Axiliarmu\Desktop\unibag proyecto"
powershell -ExecutionPolicy Bypass -File .\scale\scale_bridge.ps1 -Mode stub
```

## Ejecutar (modo serial)
Ejemplo (ajustar COM y parámetros):

```powershell
cd "c:\Users\Axiliarmu\Desktop\unibag proyecto"
powershell -ExecutionPolicy Bypass -File .\scale\scale_bridge.ps1 -Mode serial -ComPort COM3 -BaudRate 9600 -Parity None -DataBits 8 -StopBits One
```

Si la balanza requiere comando de solicitud:

```powershell
powershell -ExecutionPolicy Bypass -File .\scale\scale_bridge.ps1 -Mode serial -ComPort COM3 -RequestCommand "P"
```

## Configurar el sistema PHP
En `.env`:

```ini
SCALE_MODE=http
SCALE_HTTP_URL=http://localhost:8765/weight
```

