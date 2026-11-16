#!/usr/bin/env bash
# ==============================================================================
# PreventiTI — Gestión de Usuarios y Permisos POSIX (Ubuntu Server 22.04 LTS)
# Autor: Ignacio "Nacho" de la Rosa (Especialista en Infraestructura y Redes)
# Tarea Hito 1: Paso 1.5 / RNF-07
# ==============================================================================

set -euo pipefail
if [[ $(uname -s) != Linux || $EUID -ne 0 ]]; then
    echo 'Ejecutar con sudo en el servidor Linux de destino.' >&2
    exit 1
fi
APP_DIR="${APP_DIR:-/var/www/preventiti}"
DEPLOY_USER="${DEPLOY_USER:-${SUDO_USER:-nacho}}"
if [[ "$APP_DIR" != /var/www/* || ! -d "$APP_DIR" || -L "$APP_DIR" ]]; then
    echo 'APP_DIR debe ser un directorio real de despliegue dentro de /var/www.' >&2
    exit 1
fi
APP_DIR=$(realpath "$APP_DIR")
[[ "$APP_DIR" == /var/www/* ]] || exit 1
if [[ "$DEPLOY_USER" == www-data ]] || ! id "$DEPLOY_USER" >/dev/null 2>&1; then
    echo 'DEPLOY_USER debe ser una cuenta administrativa existente, distinta de www-data.' >&2
    exit 1
fi

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
usermod -aG app_preventiti "$DEPLOY_USER"

# 3. Asignar propiedad y permisos a los directorios de almacenamiento de Laravel
if [ -d "$APP_DIR" ]; then
    echo "[+] Ajustando permisos sobre $APP_DIR..."
    chown -hR "$DEPLOY_USER":app_preventiti "$APP_DIR"

    # Permisos generales
    find "$APP_DIR" -type d -exec chmod 2750 {} +
    find "$APP_DIR" -type f -exec chmod u=rwX,g=rX,o= {} +

    # www-data solo escribe en los directorios de ejecución de Laravel.
    for directory in "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"; do
        find "$directory" -type d -exec chmod 2770 {} +
        find "$directory" -type f -exec chmod 660 {} +
    done
    if [[ -f "$APP_DIR/.env" ]]; then chmod 640 "$APP_DIR/.env"; fi

    echo "[+] Código administrado por $DEPLOY_USER; escritura web limitada a storage y bootstrap/cache."
else
    echo "[!] Advertencia: $APP_DIR no existe aún. Ejecutar cuando el código esté clonado."
fi

echo "[✓] Configuración de permisos completada exitosamente."
