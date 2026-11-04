# Migración integral al Hito 1

## Modelo implementado

El MER editable es [PREVENTITI_MER_HITO1.drawio](../arquitectura/PREVENTITI_MER_HITO1.drawio). El archivo fuente anterior solo estaba disponible en Drive en modo de lectura y no existía una copia editable dentro del repositorio; por eso se incorpora este fuente `.drawio` local como el MER mantenible del proyecto. El modelo de dominio incorpora `usuarios`, `usuario_nombres`, `usuario_direcciones`, `tecnicos`, `jefes_tecnicos`, `jefe_nombres`, `tecnico_jefe_tecnico`, `reportes`, `usuario_reporte`, `reporte_activo`, `auditorias`, `activos`, `activo_ubicaciones`, `alertas`, `activo_alerta`, `mantenimientos`, `categorias_activo` y `consumibles`.

Las relaciones N:M del Hito se materializan con claves primarias compuestas. El vínculo Reporte–Auditoría es 1:1 mediante `reportes.auditoria_id UNIQUE`; Activo–Ubicación es 1:1; Categoría–Activo, Activo–Consumible, Jefe–Reporte y Activo–Mantenimiento son 1:N.

## Comparación y migración de datos

| Modelo anterior | Modelo Hito 1 | Tratamiento |
| --- | --- | --- |
| `roles` + `usuarios.rol_id` | `usuarios.rol` | Se convierte a Administrador o Empleado; la jerarquía técnica se deriva de Técnico/Jefe Técnico. |
| `usuarios.nombre`, `sector` | Usuario Nombre / Dirección | Se separa nombre/apellido y se conserva el sector como calle de referencia. |
| `ubicaciones` + `activos.ubicacion_id` | Activos Ubicación | Se crea una ubicación 1:1 por activo, conservando nombre y sector como piso/sala. |
| reporte con activo/creador directos | Usuario Reporte + Reporte Activo | Se conservan IDs y relaciones en pivotes compuestos. |
| `notificaciones` | Alerta + Activos Alerta | Se genera una alerta por reporte; destinatarios, títulos, mensajes y lectura históricos se preservan como descripciones de Auditoría. |
| `auditoria_logs` | Auditoría | Cada log se migra como una auditoría descriptiva. |
| mantenimiento con técnico y próxima fecha | Mantenimiento | Técnico y próxima fecha histórica se preservan en observaciones; la próxima fecha actual se deriva de frecuencia de servicio. |
| `consumibles_activo` | Consumible | Tipo y nivel se trasladan; el umbral operativo es el 20 %. |

No se eliminan datos silenciosamente: los códigos, nombres y responsables de activos que no tienen atributo propio en el Hito quedan incorporados a `activos.descripcion`. Los metadatos de categoría sin atributo destino quedan en su descripción.

### Compatibilidad del CRUD de activos — 22/10/2026

Las nuevas altas y ediciones guardan esos metadatos en el mismo campo `activos.descripcion` mediante JSON con `_format: preventiti.asset.v1`, `codigo`, `nombre`, `responsable` y `descripcion_original`. La descripción previa se conserva íntegra en la primera edición y no se anida de nuevo en ediciones posteriores. El modelo `Asset` sigue leyendo el formato anterior con etiquetas `Código legado`, `Nombre legado` y `Responsable legado`; ambos formatos devuelven los mismos atributos a las vistas. Los nuevos valores admiten puntos, comillas y signos sin truncarse. En las altas, un código nulo se resuelve como `ACT-` seguido del ID y se conserva al editar.

La implementación utiliza el esquema existente y no requiere una migración adicional. Las relaciones de reportes, mantenimientos, tareas y consumibles se conservan. Ver [contrato y verificación del CRUD](CRUD_ACTIVOS.md).

## Decisiones técnicas y ambigüedades documentadas

- El Hito destaca solo `ID_Tecnico` como PK de Técnico–Jefe Técnico; se implementa exactamente una asignación vigente por técnico y se admite `ID_Reporte` nulo hasta que se asigne un reporte.
- El MER y el pasaje a tablas expresan Usuario–Reporte y Reporte–Activo como relaciones normalizadas; por ello prevalecen las tablas intermedias con PK compuesta.
- El Hito no contiene credenciales, estado de cuenta, sesiones, caché, colas ni calendario. `password`, `activo`, `remember_token` y `tareas_calendario` se mantienen solo como soporte técnico para no romper autenticación ni calendario; no se muestran en el MER académico.
- El Hito define Rol con dos valores. Los permisos de Técnico y Jefe Técnico se obtienen de sus entidades, sin crear un tercer rol de negocio.

## Ejecución y verificación

- La migración reenlaza los datos existentes sin renumerar IDs: `2026_09_22_000001_migrate_preventiti_to_hito1_schema.php`.
- La conciliación posterior `2026_09_22_000002_reconcile_official_catalog_and_staff.php` ajusta los datos a las capturas oficiales: 69 activos en 7 categorías y 40 usuarios (3 administradores, 1 técnico líder, 6 técnicos y 30 empleados). Al no existir nombres ni identificadores para los dos técnicos adicionales, se usan dos registros de demostración claramente rotulados hasta recibir los datos oficiales.
- Antes de la conciliación se conserva `database/backups/pre-catalogo-oficial-20260922.sqlite`. Los reportes, tareas y auditorías siguen disponibles; cuando un activo sale del alcance, se elimina sólo el vínculo operativo que impediría retirar el activo y se registra el antecedente en Auditoría.
- El DDL para MariaDB/MySQL está en [mariadb_schema.sql](../../infrastructure/database/mariadb_schema.sql).
- Se ejecutó `php artisan test`: 16 pruebas aprobadas. Cubren esquema, restricciones de acceso, pivotes, alerta, mantenimiento, autenticación y rutas críticas.
- Se creó el respaldo `database/backups/preventiti-pre-hito1-20260922.sqlite`, se aplicó la migración hacia adelante sobre la SQLite local y se verificaron 38 usuarios, 78 activos, 8 reportes, 5 mantenimientos, 22 consumibles y 50 auditorías. También respondieron correctamente `GET /api/dashboard/stats` y `GET /api/reportes` contra esa base migrada.
- No había una conexión ni cliente MySQL/MariaDB configurados en este entorno; el DDL fue alineado y entregado, pero no se ejecutó contra un servidor MySQL.

La migración no tiene `down()` automático, porque reconstruye tablas y transforma información; para revertirla se debe restaurar el respaldo previo a ejecutarla.
