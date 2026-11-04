# CRUD de activos — implementación y verificación

Fecha: 22/10/2026. Estado: implementado y verificado localmente en Laravel/PHP con SQLite.

## Uso

Ingresar con una cuenta de administrador y abrir **Inventario**.

1. **Alta de Nuevo Activo** abre el formulario de creación.
2. La búsqueda, los filtros y la paginación permiten localizar el registro. **Ficha Técnica** consulta su información actual en el servidor.
3. El botón de lápiz **Editar activo**, o la misma acción dentro de la ficha, carga los datos actuales para modificarlos.
4. **Guardar cambios** confirma el guardado en la base y actualiza los datos de la interfaz. Una recarga del navegador conserva lo guardado.
5. **Dar de baja lógica** solicita confirmación y cambia el estado a `Retirado`. **Revertir baja y recuperar estado anterior** recupera el estado anterior registrado.

El administrador puede modificar categoría, ubicación, nombre/modelo, número de serie, responsable y frecuencia de mantenimiento. Nombre, categoría y serie son obligatorios; la serie debe ser única, con un máximo de 100 caracteres. El nombre admite 200 caracteres y el responsable 150. El responsable vacío queda como `No asignado`. La frecuencia se valida entre 1 y 3650 días; las altas sin frecuencia enviada por API mantienen el valor predeterminado de 90 días.

La edición conserva el código, la fecha de alta, el estado operativo, el antecedente de retiro y las relaciones históricas. Si se mueve el activo, una posición de mapa confirmada en la sala anterior se identifica como obsoleta hasta volver a confirmarla. Los consumibles existentes conservan sus niveles; una categoría que requiere consumible crea uno al 100 % solo si aún no existe.

## API y persistencia

Todas las rutas requieren sesión y protección CSRF para las escrituras del navegador.

| Operación | Ruta | Permiso |
| --- | --- | --- |
| Listado | `GET /api/activos` | Administrador, jefe técnico y técnicos |
| Ficha | `GET /api/activos/{id}` | Política de consulta de inventario |
| Alta | `POST /api/activos` | Administrador |
| Modificación | `PATCH /api/activos/{asset}` | Administrador |
| Baja lógica / reversión | `PATCH /api/activos/{id}/toggle` | Administrador |
| Baja física excepcional | `DELETE /api/activos/{id}` | Jefe técnico, solo sin reportes ni mantenimientos |

La edición recibe `categoria_id`, `ubicacion_id`, `nombre`, `serie`, `responsable`, `frecuencia_service` y `expected_version`. Este último debe copiarse de `data.asset.edit_version` de la consulta de ficha. Una versión desactualizada devuelve HTTP 409 y conserva los cambios ya confirmados por otra sesión. La interfaz mantiene los datos ingresados y permite cargar la versión actual. Los errores de validación devuelven HTTP 422; la edición sin autorización devuelve 401 o 403 según corresponda.

`ubicacion_id: null` permite conservar una ubicación histórica que no figure en el catálogo actual. Omitir `responsable` o `frecuencia_service` en una edición conserva su valor; enviar el responsable explícitamente vacío lo deja sin asignar. La serie propia puede conservarse, pero no repetirse en otro activo. Solo los campos validados se utilizan para actualizar.

Activo, ubicación, alta eventual del consumible y auditoría se guardan en una transacción. La auditoría registra usuario, fecha y valores anteriores/nuevos de los campos modificados. El modelo mantiene compatibilidad con las descripciones históricas y utiliza un formato JSON versionado en el mismo campo para las altas y ediciones; ver [compatibilidad del modelo Hito 1](MIGRACION_HITO1.md).

## Evidencia de verificación

Entorno: PHP 8.4.23, Node 24.18.0, SQLite. Los cambios no requieren una nueva migración del esquema.

| Comprobación | Resultado |
| --- | --- |
| `php artisan test --compact` | 66 pruebas aprobadas; 698 aserciones |
| `node --test tests/Frontend/*.test.mjs` | 24 pruebas aprobadas |
| `npm run build` | Compilación correcta de estilos y recursos locales |
| Navegador, base SQLite temporal independiente | Alta, consulta, edición, error por serie duplicada, recarga con persistencia, baja lógica y recuperación comprobadas |

Las pruebas PHP emplean SQLite en memoria. Para el navegador se inicializó otra SQLite con los datos semilla: 69 activos y 40 usuarios. Se creó un activo de prueba, se modificaron sus seis campos y se constató su persistencia después de recargar. Las operaciones se realizaron sobre esa base aislada.

La regresión incorporada en `tests/Feature/AssetCrudTest.php` comprueba el ciclo CRUD, texto con puntuación y símbolos, serie propia/duplicada, datos inválidos, permisos, CSRF, conflicto entre versiones, conservación de código y descripción original, historial, estados de retiro, ubicación/mapa, consumibles y reversión de toda la transacción ante un fallo de auditoría. Las pruebas existentes cubren además los filtros y las reglas de baja lógica.

La [captura del formulario con datos recuperados](../../output/verificacion-crud-20261022/edicion-activo.png) y los [resultados registrados](../../output/verificacion-crud-20261022/resultados.json) acompañan esta verificación.

El cierre acredita el CRUD local. La ejecución en MariaDB y la presentación efectiva en el parcial requieren su propia evidencia de laboratorio y del equipo.
