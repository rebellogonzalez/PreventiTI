#!/usr/bin/env bash
# ==============================================================================
# PreventiTI — Gestión de Usuarios y Permisos POSIX (Ubuntu Server 22.04 LTS)
# Autor: Ignacio "Nacho" de la Rosa (Especialista en Infraestructura y Redes)
# Tarea Hito 1: Paso 1.5 / RNF-07
# ==============================================================================

set -euo pipefail

echo "[+] Inicializando configuración de usuarios y permisos para PreventiTI..."

# 1. Crear grupo de aplicación si no existe
if ! getent group app_preventiti >/dev/null; then
    groupadd -r app_preventiti
    echo "[+] Grupo 'app_preventiti' creado exitosamente."
else
    echo "[*] El grupo 'app_preventiti' ya existe."
fi

# 2. Agregar usuario web (www-data) y administrador al grupo
usermod -aG app_preventiti www-data
if id -u nacho >/dev/null 2>&1; then
    usermod -aG app_preventiti nacho
fi

# 3. Asignar propiedad y permisos a los directorios de almacenamiento de Laravel
APP_DIR="/var/www/preventiti"

if [ -d "$APP_DIR" ]; then
    echo "[+] Ajustando permisos sobre $APP_DIR..."
    chown -R www-data:app_preventiti "$APP_DIR"

    # Permisos generales
    find "$APP_DIR" -type d -exec chmod 755 {} \;
    find "$APP_DIR" -type f -exec chmod 644 {} \;

    # Permisos de escritura para storage y bootstrap/cache (775 en carpetas, 664 en archivos)
    chmod -R 775 "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
    find "$APP_DIR/storage" -type f -exec chmod 664 {} \;
    find "$APP_DIR/bootstrap/cache" -type f -exec chmod 664 {} \;

    echo "[+] Permisos 775/664 aplicados correctamente en storage/ y bootstrap/cache/."
else
    echo "[!] Advertencia: $APP_DIR no existe aún. Ejecutar cuando el código esté clonado."
fi

echo "[✓] Configuración de permisos completada exitosamente."
