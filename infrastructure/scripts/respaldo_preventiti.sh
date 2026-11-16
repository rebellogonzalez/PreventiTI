#!/usr/bin/env bash
# Respaldo con el servicio verificado de Laravel; sin contraseñas en argumentos.
set -euo pipefail
APP_PATH="${APP_PATH:-/var/www/preventiti}"
PHP_BIN="${PHP_BIN:-php}"
cd "$APP_PATH"
exec "$PHP_BIN" scripts/database/backup.php
