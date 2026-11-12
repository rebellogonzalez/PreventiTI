# ==============================================================================
# PreventiTI — Extracción y Auditoría Forense de Eventos (Windows Server 2022)
# Autor: Ignacio "Nacho" de la Rosa
# Tarea Hito 2: Paso 2.6 / Hito 3: Paso 3.4
# ==============================================================================

$ExportPath = "C:\AuditLogs"
if (!(Test-Path $ExportPath)) {
    New-Item -ItemType Directory -Path $ExportPath -Force | Out-Null
}

$Timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
$OutputFile = "$ExportPath\SecurityAudit_$Timestamp.csv"

Write-Host "[+] Extrayendo eventos de seguridad de Active Directory DS..." -ForegroundColor Cyan

# Eventos clave:
# 4624: Inicio de sesión exitoso
# 4625: Inicio de sesión fallido (control de GPO 3 intentos)
# 4740: Cuenta de usuario bloqueada
$EventIDs = @(4624, 4625, 4740)

$Events = Get-WinEvent -FilterHashtable @{
    LogName = 'Security'
    Id = $EventIDs
    StartTime = (Get-Date).AddDays(-1)
} -ErrorAction SilentlyContinue

if ($Events) {
    $Events | Select-Object @{Name='TimeCreated'; Expression={$_.TimeCreated.ToString('yyyy-MM-dd HH:mm:ss', [cultureinfo]::InvariantCulture)}}, Id, LevelDisplayName, Message |
        Export-Csv -Path $OutputFile -NoTypeInformation -Encoding UTF8
    Write-Host "[✓] Auditoría exportada exitosamente a: $OutputFile" -ForegroundColor Green
} else {
    Write-Host "[*] No se registraron eventos críticos en las últimas 24 horas." -ForegroundColor Yellow
}
