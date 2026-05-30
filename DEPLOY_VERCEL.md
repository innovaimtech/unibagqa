# Despliegue en Vercel

## Acceso recomendado
- Crear un token temporal en Vercel:
  - `Vercel Dashboard -> Settings -> Tokens -> Create`
- Compartir solo el token, no la contraseña de la cuenta.

## Variables de entorno mínimas
- `APP_ENV=production`
- `ERP_DB_HOST=...`
- `ERP_DB_PORT=3306`
- `ERP_DB_NAME=unibagqa`
- `ERP_DB_USER=...`
- `ERP_DB_PASS=...`
- `ERP_DB_CHARSET=utf8mb4`
- `TRZ_DB_HOST=...`
- `TRZ_DB_PORT=3306`
- `TRZ_DB_NAME=unibag_trazabilidad`
- `TRZ_DB_USER=...`
- `TRZ_DB_PASS=...`
- `TRZ_DB_CHARSET=utf8mb4`
- `SESSION_SAVE_PATH=/tmp/unibag-sessions`
- `SCALE_MODE=off`
- `PRINT_MODE=none`

## Requisitos importantes
- Las bases de datos deben aceptar conexiones desde internet o desde una red accesible por Vercel.
- La balanza y la impresión local quedan desactivadas en Vercel.
- Si `unibagqa` está solo en XAMPP/local, Vercel no podrá conectarse.

## Comandos CLI
```bash
vercel login
vercel link
vercel env add APP_ENV
vercel env add ERP_DB_HOST
vercel env add ERP_DB_PORT
vercel env add ERP_DB_NAME
vercel env add ERP_DB_USER
vercel env add ERP_DB_PASS
vercel env add ERP_DB_CHARSET
vercel env add TRZ_DB_HOST
vercel env add TRZ_DB_PORT
vercel env add TRZ_DB_NAME
vercel env add TRZ_DB_USER
vercel env add TRZ_DB_PASS
vercel env add TRZ_DB_CHARSET
vercel env add SESSION_SAVE_PATH
vercel env add SCALE_MODE
vercel env add PRINT_MODE
vercel --prod
```

## Uso con token
```bash
setx VERCEL_TOKEN "TU_TOKEN"
vercel link --token %VERCEL_TOKEN%
vercel --prod --token %VERCEL_TOKEN%
```

## Estado actual del proyecto
- `vercel.json` ya redirige todo a `api/index.php`.
- La sesión ya soporta ruta temporal compatible con entornos serverless.
- La balanza respeta `SCALE_MODE=off` y no simula peso en cloud.
