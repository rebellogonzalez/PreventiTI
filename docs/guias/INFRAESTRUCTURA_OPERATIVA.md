# Infraestructura operativa — 22 de octubre de 2026

El módulo consulta el servidor que ejecuta la aplicación: sistema operativo, versión PHP, conexión de lectura con la base, espacio de disco, carga media del sistema y memoria de la petición PHP. Cada lectura lleva su fecha; se actualiza al abrir la vista o pulsar **Actualizar estado**. La carga media no representa un porcentaje de CPU y la memoria de PHP no es la RAM total.

La topología aprobada de VLAN 10, VLAN 20, pfSense y WireGuard se conserva como referencia. La pantalla no afirma que esos equipos estén activos sin una medición que lo demuestre.

## Comprobación TCP

**Comprobar puertos** intenta conexiones TCP reales desde el servidor de PreventiTI. Por defecto utiliza el host y puerto de `APP_URL`, además de 22, 443 y 3306. El administrador del despliegue puede fijar `INFRASTRUCTURE_SCAN_HOST` y `INFRASTRUCTURE_SCAN_PORTS` en su entorno; la petición HTTP no puede elegir destinos, puertos u origen. Se permiten hasta ocho puertos, con tiempo de conexión limitado. La prueba no usa Nmap ni simula un origen VLAN 10. Una conexión fallida no basta para distinguir un servicio cerrado, un problema de ruta o un firewall.

## Respaldos reales

**Crear respaldo** guarda una instantánea consistente de la SQLite activa mediante la API de respaldo de SQLite e incluye los archivos normales de `storage/app`. Soporta datos confirmados en WAL. Se comprueba la integridad y claves foráneas de la copia, se construye un ZIP y se relee cada archivo para comparar su tamaño y SHA-256. El manifiesto indica la ventana de copia: base y archivos no forman una única transacción conjunta.

Las copias quedan fuera de `public/` y de los discos de archivos servibles, en `storage/backups/preventiti`, con directorio privado y archivos restringidos. La descarga exige una sesión de administrador o jefe técnico y vuelve a verificar el SHA-256. Se impiden rutas arbitrarias, enlaces simbólicos y respaldos simultáneos. Si falla una comprobación, no se informa éxito. Las copias anteriores no se eliminan ni rotan automáticamente.

Requisitos: base SQLite persistente y extensiones PHP `sqlite3` y `zip`. Para MariaDB u otro motor el módulo informa que requiere un procedimiento específico; no inventa un dump. El script histórico MariaDB sigue conservado en `infrastructure/scripts/respaldo_preventiti.sh`, pero no se ejecuta desde la interfaz. Los ZIP no incluyen código, `.env`, configuración de red ni servicios externos. Conviene descargar una copia fuera del servidor; un archivo en el mismo disco no protege frente a la pérdida de ese disco.

Antes de cualquier restauración, extraer en otra ubicación, verificar el manifiesto y comprobar la SQLite extraída. Nunca sobrescribir automáticamente la base activa: puede contener registros posteriores al respaldo. Las pruebas automatizadas restauran una copia ficticia y verifican los datos recuperados.

## Baja lógica y condición operativa

`Fuera de Servicio` indica la condición del dispositivo; `Retirado` indica su baja patrimonial. La acción de baja guarda la condición anterior en `activos.estado_previo_retiro`. Al revertirla recupera esa condición, sin modificar reportes, mantenimientos ni consumibles. Una baja antigua sin condición previa registrada vuelve a `Fuera de Servicio` en vez de presumir que el equipo funciona. Las peticiones de la interfaz incluyen el estado esperado para rechazar acciones sobre información desactualizada.

La migración `2026_10_22_000001_preserve_asset_state_before_retirement` solo agrega una columna nullable. No transforma estados actuales ni elimina registros. La disponibilidad operativa `activo` conserva su significado para las demás reglas; la baja lógica se consulta por separado con `retirado`.
