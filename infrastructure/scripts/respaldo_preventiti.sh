#!/usr/bin/env bash
# ==============================================================================
# PreventiTI — Respaldo Automatizado DRP (Base de Datos MariaDB + Storage)
# Autor: Daniel Sanz / Ignacio de la Rosa
# Tarea Hito 2: Paso 2.6 / Hito 3: Paso 3.2 (Caso de Prueba CP-05)
# ==============================================================================

set -euo pipefail

BACKUP_DIR="/var/backups/preventiti"
DATE_TAG=$(date +"%Y%m%d_%H%M%S")
DB_NAME="${DB_DATABASE:-preventiti}"
DB_USER="${DB_USERNAME:-preventiti_user}"
DB_PASS="${DB_PASSWORD:-preventiti_secure_2026}"
APP_PATH="/var/www/preventiti"

mkdir -p "$BACKUP_DIR"

echo "[$(date)] Iniciando ciclo de respaldo DRP para PreventiTI..."

# 1. Volcado lógico de MariaDB
SQL_FILE="$BACKUP_DIR/${DB_NAME}_${DATE_TAG}.sql"
echo "[+] Exportando base de datos MariaDB..."
if command -v mysqldump >/dev/null; then
    mysqldump -u"$DB_USER" -p"$DB_PASS" --single-transaction --routines --triggers "$DB_NAME" > "$SQL_FILE"
    echo "[✓] Volcado SQL generado: $SQL_FILE"
else
    # Fallback si se ejecuta sobre sqlite
    if [ -f "$APP_PATH/database/database.sqlite" ]; then
        sqlite3 "$APP_PATH/database/database.sqlite" ".dump" > "$SQL_FILE"
        echo "[✓] Volcado SQLite generado: $SQL_FILE"
    fi
fi

# 2. Empaquetar y comprimir dump SQL + directorio storage
ARCHIVE_FILE="$BACKUP_DIR/preventiti_backup_${DATE_TAG}.tar.gz"
echo "[+] Comprimiendo respaldo completo..."
tar -czf "$ARCHIVE_FILE" -C "$BACKUP_DIR" "$(basename "$SQL_FILE")" -C "$APP_PATH" "storage/app"

# 3. Limpieza del archivo SQL plano temporal
rm -f "$SQL_FILE"

# 4. Rotación de respaldos (mantener últimos 7 días)
find "$BACKUP_DIR" -name "preventiti_backup_*.tar.gz" -type f -mtime +7 -delete

echo "[✓] Respaldo finalizado con éxito: $ARCHIVE_FILE ($(du -h "$ARCHIVE_FILE" | cut -f1))"
