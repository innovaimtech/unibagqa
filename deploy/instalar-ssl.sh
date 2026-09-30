#!/usr/bin/env bash
# ==============================================================================
# Script para activar Certificado SSL Gratuito (HTTPS) con Let's Encrypt / Certbot
# Dominio: unibag.innovaimtech.online
# ==============================================================================

set -e

DOMAIN="unibag.innovaimtech.online"
APP_DIR="/var/www/innovaimtech/unibagqa"

echo "======================================================"
echo "🔒 Configurando Sitio Seguro (SSL/HTTPS) para $DOMAIN"
echo "======================================================"

# 1. Comprobar permisos de root
if [ "$EUID" -ne 0 ]; then
  echo "❌ Por favor ejecuta este script como root o con sudo: sudo bash deploy/instalar-ssl.sh"
  exit 1
fi

# 2. Instalar Certbot y plugin de Nginx si no están instalados
echo "📦 Verificando instalación de Certbot..."
apt update -y
apt install -y certbot python3-certbot-nginx

# 3. Asegurar puertos en Firewall (UFW) si está activo
echo "🛡️  Verificando reglas de firewall..."
if command -v ufw >/dev/null 2>&1; then
    ufw allow 'Nginx Full' 2>/dev/null || true
    ufw allow 80/tcp 2>/dev/null || true
    ufw allow 443/tcp 2>/dev/null || true
fi

# 4. Asegurar archivo de configuración base de Nginx
echo "⚙️  Verificando configuración de Nginx..."
if [ -f "$APP_DIR/deploy/nginx/unibag.conf" ]; then
    cp "$APP_DIR/deploy/nginx/unibag.conf" /etc/nginx/sites-available/unibag.conf
    ln -sf /etc/nginx/sites-available/unibag.conf /etc/nginx/sites-enabled/unibag.conf
    nginx -t && systemctl reload nginx
fi

# 5. Obtener e instalar el certificado SSL automáticamente con Certbot
echo "🔑 Obteniendo e instalando certificado SSL con Certbot..."
certbot --nginx -d "$DOMAIN" --non-interactive --agree-tos -m admin@innovaimtech.online --redirect

# 6. Probar renovación automática
echo "🔄 Probando renovación automática de certificados..."
certbot renew --dry-run

echo "======================================================"
echo "✅ ¡Sitio seguro configurado con éxito!"
echo "👉 Ahora puedes ingresar a: https://$DOMAIN"
echo "======================================================"
