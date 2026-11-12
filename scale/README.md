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
Ejemplo (ajustar COM y parÃ¡metros):

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


## Puerto automÃ¡tico (`-ComPort AUTO`, el que usa `iniciar_proyecto.bat`)
- Prueba cada puerto COM de **este** computador (primero los adaptadores USB-serie CH340/Prolific/FTDI/CP210; omite Bluetooth)
  y se queda con el que envÃ­a una trama de peso vÃ¡lida. Antes abrÃ­a siempre el primero (normalmente COM1) y no probaba los demÃ¡s.
- Si el cable se desconecta, o el puerto pasa 10 lecturas sin datos, vuelve a buscar (como mÃ¡ximo cada 5 s).
- DiagnÃ³stico: `http://localhost:8765/ports` (puertos con su nombre de Windows, puerto activo y resultado de la Ãºltima bÃºsqueda).
- Un puerto COM lo puede usar un solo programa a la vez: si la balanza estÃ¡ vinculada en el navegador (Web Serial), el puente no puede abrirla, y al revÃ©s.

