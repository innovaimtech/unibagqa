#!/usr/bin/env bash
# ==============================================================================
# Script de despliegue y actualización automática para VPS (Unibag ERP)
# ==============================================================================

set -e

APP_DIR="/var/www/innovaimtech/unibagqa"

echo "=========================================="
echo "🚀 Iniciando actualización Unibag ERP..."
echo "=========================================="

if [ -d "$APP_DIR" ]; then
    cd "$APP_DIR"
fi

# 1. Asegurar excepción de directorio seguro de git
git config --global --add safe.directory "$APP_DIR" 2>/dev/null || true

# 2. Descargar últimos cambios de GitHub
echo "📥 Descargando cambios desde GitHub (main)..."
git pull origin main

# 3. Aplicar migraciones si existen
if [ -d "database/migrations" ]; then
    echo "🗄️  Verificando migraciones SQL..."
    for file in database/migrations/*.sql; do
        if [ -f "$file" ]; then
            mysql unibag_trazabilidad < "$file" 2>/dev/null || true
        fi
    done
fi

# 4. Asegurar permisos de carpetas de escritura
echo "🔒 Ajustando permisos de carpetas de almacenamiento..."
mkdir -p storage/sessions storage/logs data
chown -R www-data:www-data storage data 2>/dev/null || true
chmod -R 775 storage data 2>/dev/null || true

# 5. Deshabilitar módulos de debug si estuvieran activos (uopz, xdebug)
phpdismod uopz xdebug 2>/dev/null || true

# 6. Reiniciar servicios PHP-FPM y Nginx
echo "🔄 Reiniciando servicios web..."
systemctl restart php8.1-fpm 2>/dev/null || systemctl restart php*-fpm 2>/dev/null || true
systemctl restart nginx 2>/dev/null || true

echo "=========================================="
echo "✅ ¡Actualización completada con éxito!"
echo "=========================================="
