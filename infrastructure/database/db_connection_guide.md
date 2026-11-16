# Integración local de MariaDB — 15 de noviembre de 2026

PreventiTI utiliza la conexión Laravel `mariadb`, MariaDB **10.11.19**, InnoDB, UTF-8 y hora UTC. La migración `2026_11_15_000001_install_mariadb_business_rules.php` instala `database/sql/mariadb_rules.sql`. El DDL histórico `mariadb_schema.sql` no sustituye las migraciones actuales. La instalación de reglas exige la serie **10.11.x**; MySQL 8 y otras series no están validados.

## Instalación privada en este equipo

Directorio permanente: `/Users/ramirorebello/PreventiTI-MariaDB-integracion-20261115`, permiso 0700. No depende de los archivos temporales del laboratorio anterior.

| Elemento | Función |
| --- | --- |
| `runtime/datos` | Archivos del servidor |
| `runtime/mariadb.sock` | Conexión local; TCP deshabilitado |
| `runtime/paquetes` | Paquetes originales con versiones y comprobaciones registradas |
| `privado` | Contraseñas nuevas, opciones de clientes y exportación privada; archivos 0600 |
| `respaldo/database.sqlite` | Instantánea SQLite previa al cambio |
| `respaldo/env.sqlite.original` | Configuración anterior, incluida la clave de la aplicación |
| `respaldo/proyecto.bundle` | Historial Git anterior a la integración |
| `backups` | ZIP completos y respaldos anteriores conservados |
| `evidencia` | Resultados de pruebas, comparación y restauración |

El agente de usuario `net.preventiti.mariadb1011`, en `~/Library/LaunchAgents/net.preventiti.mariadb1011.plist`, inicia MariaDB al entrar en la sesión macOS y lo reinicia si termina. Esta instalación local no configura la VM, un servidor remoto ni un servicio anterior al inicio de sesión.

La base operativa es `preventiti`. `preventiti_app` puede leer, escribir datos y ejecutar rutinas; no dispone de DDL. Las migraciones requieren el administrador. Las pruebas utilizan `preventiti_tests` y las restauraciones `preventiti_restore_tests`. Las cuentas y sus hashes originales se preservan. No se publican datos ni credenciales en Git.

Desde la raíz del proyecto:

```sh
python3 -B scripts/database/local_mariadb.py --home "/Users/ramirorebello/PreventiTI-MariaDB-integracion-20261115" status
launchctl print "gui/$(id -u)/net.preventiti.mariadb1011"
php artisan serve
```

Detener el servicio: `launchctl bootout "gui/$(id -u)/net.preventiti.mariadb1011"`. Habilitarlo de nuevo: `launchctl bootstrap "gui/$(id -u)" "$HOME/Library/LaunchAgents/net.preventiti.mariadb1011.plist"`. No detener solo el proceso con `local_mariadb.py stop` mientras `KeepAlive` esté activo: launchd lo reiniciará.

El cierre mediante `bootout` es asíncrono. Antes de `bootstrap`, esperar hasta que `launchctl print "gui/$(id -u)/net.preventiti.mariadb1011"` indique que el servicio ya no existe.

## Reglas en la base

Se instalan **10 triggers, 3 procedimientos**, restricciones y un índice único de asignación vigente.

1. Al pasar a `Finalizado`, se completa `fecha_cierre`, se conserva el informe como resolución y las asignaciones vigentes reciben exactamente la misma `fecha_finalizacion`.
2. Se rechazan asignaciones vigentes para cuentas técnicas inactivas, perfiles inexistentes, jefaturas inactivas, reportes cerrados o el jefe como especialista. Solo se permite una asignación vigente por reporte. Desactivar una cuenta termina sus asignaciones sin retroceder el estado del reporte. Los períodos terminados no se reabren ni se reescriben.
3. Transiciones: `Recibido/Derivado → En curso → Diagnóstico → Finalizado`, y `En curso → Finalizado`. Avanzar exige un técnico vigente con cuenta activa. `Diagnóstico` exige informe y diagnóstico de 5–5000 caracteres; `Finalizado` exige informe y fecha de cierre. No se reabren cierres. `Abierto`, `Resuelto`, `Cerrado` y `Cancelado` se conservan para lectura histórica, sin introducir transiciones nuevas hacia ellos.
4. Cada cambio real de `estado_activo` inserta activo, estado anterior, estado nuevo y fecha en `historial_estados_activo`; usuario y motivo son opcionales. Repetir el mismo estado no inserta historial. Los estados son `Operativo`, `En Mantenimiento`, `Fuera de Servicio`, `Desconectado` y `Retirado`. Se conservan las reglas de retiro y reincorporación existentes.
5. Insertar una asignación vigente inicia automáticamente un reporte en `Recibido/Derivado`. Los reportes importados 172, 174 y 176 conservan sus datos; una asignación posterior, incluso confirmar el técnico actual, regulariza su paso a `En curso`.
6. `nivel` admite **0–100**. Un UPDATE de **más de 20 a 20 o menos** genera atómicamente reporte, auditoría, vínculos al activo y alerta `Consumible / Medio / Medio / Pendiente`. El reporte empieza en `Recibido/Derivado` sin inventar un creador humano. No se repite mientras siga bajo; volver a más de 20 rearma el cruce. No hay eventos retroactivos por los cuatro consumibles ya bajos ni por INSERT. El umbral aprobado es fijo: **20**; no se agregó configuración adicional.

`asignar_tecnico` y `cambiar_estado_reporte` exigen una transacción del llamador. `validar_asignacion_vigente` también se usa desde triggers. Laravel conserva autorización, validación de peticiones, intervenciones, auditoría y control de versiones; delega los cambios de estado y asignación en el motor. El controlador de consumibles solo guarda la lectura: no crea el reporte ni la alerta.

El contexto temporal `@preventiti_usuario_id`, `@preventiti_motivo` y `@preventiti_momento` se establece por operación y se restaura después. SQL directo sin contexto deja actor y motivo nulos y utiliza el reloj UTC del servidor. Este contexto no sustituye los permisos; la fecha explícita permite simulaciones y pruebas con reloj controlado.

## Respaldar y restaurar

```sh
php scripts/database/backup.php
```

La misma función está disponible en Infraestructura para los roles autorizados. El ZIP incluye `database/database.sql`, `storage/app` y un manifiesto SHA-256. El volcado incorpora datos, claves, índices, triggers y rutinas, con `--single-transaction`. No ejecutar DDL durante la copia. Base y archivos no forman una transacción conjunta; los archivos se copian y comprueban, y un cambio durante la copia rechaza el respaldo.

`MARIADB_DUMP_BINARY`, `MARIADB_BACKUP_DEFAULTS_FILE` y `PREVENTITI_BACKUP_DIRECTORY` se configuran en `.env`, ignorado por Git. El archivo privado de opciones usa una cuenta lectora; no se pasa la contraseña en argumentos del proceso. Un ZIP verificado significa integridad del archivo; la restauración se ensaya por separado.

Para restaurar, extraer el SQL en un directorio privado y cargarlo en una base vacía de prueba, nunca sobre `preventiti`:

```sh
"/Users/ramirorebello/PreventiTI-MariaDB-integracion-20261115/runtime/opt/mariadb@10.11/bin/mariadb" --defaults-file="/Users/ramirorebello/PreventiTI-MariaDB-integracion-20261115/privado/admin.cnf" --binary-mode preventiti_restore_tests < /ruta/privada/database.sql
```

El volcado contiene `DEFINER`: restaurarlo con el administrador del mismo servidor. Para otro servidor, preparar previamente cuentas, permisos y definidores y repetir las pruebas. El ZIP no incluye cuentas del servidor ni `.env`; conservar también la configuración privada en el respaldo del equipo. Recuperar `storage/app` de forma coordinada con la base.

## Volver a SQLite

El archivo original `database/database.sqlite` se conserva. Para volver a sus datos anteriores:

```sh
python3 -B scripts/database/switch_connection.py --home "/Users/ramirorebello/PreventiTI-MariaDB-integracion-20261115" --to sqlite
```

El script conserva privadamente el `.env` actual, activa mantenimiento breve, repone solo las claves de conexión originales, limpia la configuración y habilita la aplicación. Si falla, repone la configuración anterior. No cambia `APP_KEY`, ejecuta migraciones ni escribe datos en SQLite.

Los cambios realizados después en MariaDB **no aparecerán en SQLite**. Recuperarlos exige una migración inversa específica antes del cambio. Las nuevas reglas SQL, incluido historial y alerta automática de consumibles, no se instalan en SQLite. La aplicación mantiene su flujo anterior cuando utiliza ese motor.

Para reconectar la misma MariaDB, usar el mismo comando con `--to mariadb`. Comprueba que SQLite no cambió desde su exportación; si cambió, se detiene. No existe sincronización automática. Revertir la migración SQL exige restaurar un respaldo: `down()` no elimina el historial.

## Pruebas y mantenimiento

```sh
# SQLite en memoria: nunca utiliza el archivo original.
php vendor/bin/phpunit

# Reconstruye exclusivamente la base de pruebas MariaDB.
python3 -B scripts/database/local_mariadb.py --home "/Users/ramirorebello/PreventiTI-MariaDB-integracion-20261115" --role test run -- php vendor/bin/phpunit --configuration phpunit.mariadb.xml

node --test tests/Frontend/*.test.mjs
```

La clase base rechaza conexiones distintas de SQLite `:memory:` o MariaDB `preventiti_tests` antes de `RefreshDatabase`. No ejecutar `migrate:fresh`, `db:wipe` ni seeders sobre la base operativa. Para migraciones nuevas revisadas, usar respaldo previo y el administrador:

```sh
python3 -B scripts/database/local_mariadb.py --home "/Users/ramirorebello/PreventiTI-MariaDB-integracion-20261115" --role admin run -- php artisan migrate --force
```

`export_sqlite.py` abre el origen solo para lectura; exige un destino nuevo fuera del repositorio y `--allow-private-data`. Sirve para migraciones planificadas, no sincroniza bases existentes. `verify_migration.py` contrasta campos, cuentas, índices, PK y acciones referenciales con la instantánea privada. `inspect_application.php` compara hashes de manifiestos y respuestas bajo transacciones de solo lectura.

## Evidencia de aceptación

- 31 tablas y **1.298 filas originales**, **9.860 campos** comparados sin diferencias, incluidos hashes de contraseña. El historial nuevo empieza vacío. La nueva fila del registro de migraciones produce 32 tablas y 1.299 filas en el momento del cambio.
- 34 claves foráneas sin huérfanos; índices, PK y acciones referenciales originales conservados.
- 94 pruebas de backend pasan en SQLite y 100 en MariaDB; incluyen SQL directo válido e inválido para las seis reglas, API, contexto de historial y restauración de triggers y rutinas. Pasan 33 pruebas JavaScript.
- Un respaldo completo se restaura y sus datos privados se comparan con la instantánea; se vacía después la base de restauración.
- Se conserva el SHA-256 del archivo SQLite original. Resultados concretos y rutas privadas permanecen en `evidencia`, fuera del repositorio público.
