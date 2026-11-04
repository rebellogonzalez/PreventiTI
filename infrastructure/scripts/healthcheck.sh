#!/usr/bin/env bash
# ==============================================================================
# PreventiTI — Auditoría de Salud del Servidor (CPU, RAM, Disco)
# Autor: Ignacio "Nacho" de la Rosa
# Tarea Hito 3: Paso 3.4
# ==============================================================================

set -euo pipefail

echo "========================================================"
echo " PREVENTITI — AUDITORÍA DE SALUD DEL SERVIDOR UBUNTU"
echo " Fecha: $(date)"
echo " Host: $(hostname) ($(hostname -I 2>/dev/null || echo '192.168.20.10'))"
echo "========================================================"

# 1. Monitoreo de CPU
CPU_LOAD=$(uptime | awk -F'load average:' '{ print $2 }' | cut -d, -f1 | xargs)
echo "[CPU] Carga promedio (1 min): $CPU_LOAD"

# 2. Monitoreo de Memoria RAM
MEM_TOTAL=$(free -m | awk '/Mem:/ {print $2}')
MEM_USED=$(free -m | awk '/Mem:/ {print $3}')
MEM_PCT=$(awk "BEGIN {printf \"%.1f\", ($MEM_USED/$MEM_TOTAL)*100}")
echo "[RAM] Total: ${MEM_TOTAL}MB | Usada: ${MEM_USED}MB (${MEM_PCT}%)"

# 3. Monitoreo de Almacenamiento en Disco
DISK_USAGE=$(df -h / | awk 'NR==2 {print $5}' | tr -d '%')
echo "[DISCO] Partición raíz (/): ${DISK_USAGE}% en uso"

# 4. Verificación de servicios críticos
for service in nginx php8.2-fpm mariadb; do
    if systemctl is-active --quiet "$service" 2>/dev/null; then
        echo "[SERVICIO] $service: ACTIVO (En ejecución)"
    else
        echo "[SERVICIO] $service: NO DETECTADO O INACTIVO (Modo Laboratorio/Simulación)"
    fi
done

echo "========================================================"
