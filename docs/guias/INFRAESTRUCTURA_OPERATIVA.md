# Infraestructura operativa — actualizado el 15 de noviembre de 2026

El módulo consulta el servidor que ejecuta la aplicación: sistema operativo, versión PHP, conexión de lectura con la base, espacio de disco, carga media del sistema y memoria de la petición PHP. Cada lectura lleva su fecha; se actualiza al abrir la vista o pulsar **Actualizar estado**. La carga media no representa un porcentaje de CPU y la memoria de PHP no es la RAM total.

La topología aprobada de VLAN 10, VLAN 20, pfSense y WireGuard se conserva como referencia. La pantalla no afirma que esos equipos estén activos sin una medición que lo demuestre.

## Comprobación TCP

**Comprobar puertos** intenta conexiones TCP reales desde el servidor de PreventiTI. Por defecto utiliza el host y puerto de `APP_URL`, además de 22, 443 y 3306. El administrador del despliegue puede fijar `INFRASTRUCTURE_SCAN_HOST` y `INFRASTRUCTURE_SCAN_PORTS` en su entorno; la petición HTTP no puede elegir destinos, puertos u origen. Se permiten hasta ocho puertos, con tiempo de conexión limitado. La prueba no usa Nmap ni simula un origen VLAN 10. Una conexión fallida no basta para distinguir un servicio cerrado, un problema de ruta o un firewall.

## Respaldos reales

**Crear respaldo** utiliza el motor activo. Con MariaDB 10.11.x genera un volcado InnoDB transaccional con datos, claves, índices, triggers y rutinas mediante `mariadb-dump`. Con SQLite persistente utiliza su API de respaldo, incluidos datos confirmados en WAL, y comprueba integridad y claves foráneas. Ambos incluyen los archivos normales de `storage/app` y un manifiesto SHA-256; el ZIP se relee para comprobar tamaños y hashes. Base y archivos no forman una única transacción conjunta. No ejecutar DDL durante el volcado MariaDB.

Las copias quedan fuera de `public/` y de los discos servibles, con directorio privado y archivos restringidos. El destino se configura mediante `PREVENTITI_BACKUP_DIRECTORY`; por defecto es `storage/backups/preventiti`. La integración local usa una carpeta privada fuera del repositorio. La descarga exige administrador o jefe técnico y vuelve a verificar el SHA-256. Se impiden rutas arbitrarias, enlaces simbólicos y respaldos simultáneos. Las copias anteriores no se eliminan ni rotan automáticamente.

Requisitos: PHP `zip` y, según el motor, `sqlite3` o un ejecutable `mariadb-dump` y archivo privado de conexión configurados. La instalación local utiliza una cuenta lectora específica para respaldos. El script histórico `infrastructure/scripts/respaldo_preventiti.sh` se conserva como referencia, sin ejecutarse desde la interfaz. Los ZIP no incluyen código, `.env`, cuentas del servidor ni configuración de red; conservar también la configuración privada y una copia fuera del disco del servidor.

Antes de restaurar, extraer en otra ubicación y verificar el manifiesto. Comprobar la SQLite extraída o cargar el SQL MariaDB en una base vacía de prueba. Nunca sobrescribir automáticamente la base activa: puede contener registros posteriores. Las pruebas restauran datos y verifican triggers y rutinas. Procedimiento y evidencia: [integración MariaDB](../../infrastructure/database/db_connection_guide.md).

## Baja lógica y condición operativa

`Fuera de Servicio` indica la condición del dispositivo; `Retirado` indica su baja patrimonial. La acción de baja guarda la condición anterior en `activos.estado_previo_retiro`. Al revertirla recupera esa condición, sin modificar reportes, mantenimientos ni consumibles. Una baja antigua sin condición previa registrada vuelve a `Fuera de Servicio` en vez de presumir que el equipo funciona. Las peticiones de la interfaz incluyen el estado esperado para rechazar acciones sobre información desactualizada.

La migración `2026_10_22_000001_preserve_asset_state_before_retirement` solo agrega una columna nullable. No transforma estados actuales ni elimina registros. La disponibilidad operativa `activo` conserva su significado para las demás reglas; la baja lógica se consulta por separado con `retirado`.
