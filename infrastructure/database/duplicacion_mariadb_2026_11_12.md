# Duplicación de la base actual a MariaDB

Documento histórico del 12 de noviembre. La conexión activa y las reglas implementadas el 15 de noviembre se describen en la [guía de integración MariaDB](db_connection_guide.md); las limitaciones que siguen corresponden a aquella copia anterior.

- **Origen:** `database/database.sqlite` (SQLite, abierto en modo solo lectura).
- **DDL de referencia:** `infrastructure/database/mariadb_schema.sql` (sin modificar).
- **Copia generada:** `database/backups/preventiti_copia_mariadb_2026_11_12.sql`.
- **Base destino del volcado:** `preventiti_copia_2026_11_12`.
- **Contenido exportado:** 31 tablas y 1298 filas.
- **Integridad fuente:** `PRAGMA integrity_check` devolvió `ok`; `foreign_key_check` no encontró violaciones.

## Se conservó

- Datos actuales, incluidos el ledger `migrations`, las tablas de soporte Laravel (1 sesión y 16 filas de caché; las colas están vacías) y los datos operativos. Se copiaron también los hashes de contraseña necesarios para conservar las cuentas.
- Claves primarias, índices únicos y de consulta, restricciones `CHECK`/`ENUM`, tipos compatibles con MariaDB 10.11+ y motor InnoDB.
- Relaciones y acciones referenciales activas (`CASCADE`, `SET NULL`, `RESTRICT`), incluidas asignaciones históricas, tareas, inspecciones y costos.
- Flujo persistido de reportes, responsables, alertas, mantenimiento, calendario, mapa, auditoría, presupuestos y simulación.
- La lógica Laravel (servicios, controladores y comandos) sigue intacta en el proyecto; no se convirtió a rutinas MariaDB.

## Diferencias con el DDL adjunto

- El archivo adjunto define el esquema, pero no contiene los datos de la SQLite actual; este nuevo volcado agrega los datos actuales.
- La SQLite tiene 31 tablas. Tablas activas no incluidas en el DDL adjunto: `asset_map_positions`, `cache`, `cache_locks`, `failed_jobs`, `job_batches`, `jobs`, `maintenance_budgets`, `maintenance_costs`, `migrations`, `simulation_runs`.
- Se agregaron las tablas incorporadas por migraciones posteriores: `asset_map_positions`, `simulation_runs`, `maintenance_budgets` y `maintenance_costs`, además de soporte Laravel (`cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `migrations`).
- La copia refleja el esquema SQLite ya migrado: campos de baja y responsable de activos; revisión/idempotencia de reportes; integración de tareas con reportes y mantenimientos; y `alertas.reporte_id`.
- En la SQLite el valor por defecto actual de `reportes.estado_reporte` es `Abierto`; el DDL adjunto declara `Recibido/Derivado`. La copia mantiene el valor efectivo de la base fuente.
- En la tabla `tecnico_jefe_tecnico`, la SQLite activa aplica `ON DELETE CASCADE` para jefe; el DDL adjunto indica `RESTRICT`. La copia mantiene el comportamiento efectivo de SQLite.
- El DDL adjunto usa una PK simple en `reporte_activo`; la SQLite activa conserva PK compuesta (`reporte_id`, `activo_id`) y unicidad adicional por `reporte_id`. La copia conserva ambas reglas.
- El formato adjunto define valores predeterminados de MariaDB para `created_at` y actualiza `updated_at` automáticamente en varias tablas; SQLite deja esos campos sin valor predeterminado y Laravel los administra en sus escrituras.
- Tablas del DDL adjunto que no existen en la SQLite activa: ninguna.

## Triggers y acciones

- La inspección de `sqlite_master` no encontró triggers ni vistas; SQLite no tiene procedimientos almacenados. No se inventaron triggers o procedimientos en MariaDB.
- Las acciones de integridad referencial sí están presentes en el volcado mediante las claves foráneas y sus reglas de borrado.
- La lógica de aplicación vive en PHP y se conserva al no modificar el código. Para que esas funciones operen sobre el duplicado, la aplicación debe apuntar explícitamente a la base destino; el proyecto actualmente usa SQLite.
- Hay lógica del proyecto restringida explícitamente a SQLite: `simulation:refresh` comprueba el driver y el respaldo integrado (`BackupService`) solo admite SQLite. Esas funciones siguen intactas, pero no se ejecutarán contra MariaDB sin adaptación de la aplicación.

## Filas por tabla

| Tabla | Filas |
| --- | ---: |
| `activo_alerta` | 42 |
| `activo_ubicaciones` | 74 |
| `activos` | 74 |
| `alertas` | 42 |
| `asset_map_positions` | 0 |
| `auditorias` | 422 |
| `cache` | 16 |
| `cache_locks` | 0 |
| `categorias_activo` | 7 |
| `consumibles` | 16 |
| `failed_jobs` | 0 |
| `jefe_nombres` | 1 |
| `jefes_tecnicos` | 1 |
| `job_batches` | 0 |
| `jobs` | 0 |
| `maintenance_budgets` | 1 |
| `maintenance_costs` | 25 |
| `mantenimientos` | 95 |
| `migrations` | 26 |
| `password_reset_tokens` | 0 |
| `reporte_activo` | 42 |
| `reportes` | 42 |
| `sessions` | 1 |
| `simulation_runs` | 2 |
| `tareas_calendario` | 156 |
| `tecnico_jefe_tecnico` | 42 |
| `tecnicos` | 7 |
| `usuario_direcciones` | 40 |
| `usuario_nombres` | 41 |
| `usuario_reporte` | 42 |
| `usuarios` | 41 |

El SQL crea una base nueva con nombre fechado y no contiene instrucciones `DROP`; la base SQLite de origen y el DDL adjunto permanecen intactos.

El volcado contiene datos de cuentas y de sesión; se guardó con permisos locales `0600`. Trátalo como un respaldo sensible.
