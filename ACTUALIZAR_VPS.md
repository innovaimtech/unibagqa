# 🚀 Guía de Actualización del Sistema en VPS (Unibag ERP)

Esta guía contiene los comandos exactos paso a paso para actualizar la VPS cada vez que subas cambios o código nuevo a GitHub.

---

## ⚡ Método 1: Actualización Rápida (Solo cambios de código PHP/CSS/JS)

Si solo hiciste cambios en vistas, controladores o estilos (sin nuevas tablas en la base de datos), copia y pega este comando único:

```bash
cd /var/www/innovaimtech/unibagqa && git pull origin main && systemctl restart php8.1-fpm
```

---

## 📦 Método 2: Actualización Completa (Con nuevas migraciones SQL)

Si el cambio incluye nuevas tablas, columnas o migraciones en `database/migrations/`:

```bash
cd /var/www/innovaimtech/unibagqa

# 1. Bajar el código más reciente de GitHub
git pull origin main

# 2. Aplicar automáticamente cualquier migración nueva de base de datos
for file in database/migrations/*.sql; do
    echo "Aplicando $file..."
    mysql unibag_trazabilidad < "$file"
done

# 3. Asegurar permisos correctos en carpetas de almacenamiento
chown -R www-data:www-data storage data
chmod -R 775 storage data

# 4. Reiniciar servicios para vaciar caché de PHP
systemctl restart php8.1-fpm
systemctl restart nginx
```

---

## 🤖 Método 3: Script Automático con 1 solo comando (`./deploy.sh`)

Puedes ejecutar el script incluido en el proyecto que hace todas las verificaciones automáticamente:

```bash
cd /var/www/innovaimtech/unibagqa
bash deploy.sh
```

---

## 🛠️ Solución a problemas frecuentes en la VPS

### 1. Error: `fatal: detected dubious ownership in repository`
Ocurre si cambiaste de usuario (`root` vs `www-data`).
**Solución:**
```bash
git config --global --add safe.directory /var/www/innovaimtech/unibagqa
```

### 2. Error: `error: Your local changes to the following files would be overwritten by merge`
Ocurre si editaste algún archivo directamente en el servidor (excepto `.env` que está ignorado).
**Solución para descartar cambios locales y forzar lo que está en GitHub:**
```bash
cd /var/www/innovaimtech/unibagqa
git restore .
git pull origin main
```

### 3. El sistema se siente lento o vuelve a mostrar páginas duplicadas
Verifica que los módulos de debug (`uopz` y `xdebug`) sigan desactivados:
```bash
phpdismod uopz xdebug 2>/dev/null
systemctl restart php8.1-fpm
```

### 4. Ver logs de errores en tiempo real
Para ver si PHP o Nginx arrojan algún error:
```bash
# Errores de Nginx
tail -f /var/log/nginx/error.log

# Errores de PHP-FPM
tail -f /var/log/php8.1-fpm.log
```
*(Para salir de la vista de logs presiona `Ctrl + C`)*.

---

## 📋 Resumen de Servicios del Servidor

| Acción | Comando |
| :--- | :--- |
| **Reiniciar PHP-FPM** | `systemctl restart php8.1-fpm` |
| **Reiniciar Nginx** | `systemctl restart nginx` |
| **Ver estado de PHP** | `systemctl status php8.1-fpm` |
| **Ver estado de Nginx** | `systemctl status nginx` |
| **Ver estado de MySQL** | `systemctl status mysql` |
