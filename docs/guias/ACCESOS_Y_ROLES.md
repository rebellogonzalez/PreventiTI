# Accesos y roles de PreventiTI

Actualizado el 21 de octubre de 2026. Esta matriz reemplaza las descripciones anteriores de acceso por demostración. Las cuentas existentes se conservan: 3 administradores, 1 jefe técnico, 6 técnicos especialistas y 30 empleados.

## Jerarquía y responsabilidades

| Operación | Administrador (1) | Jefe técnico (2) | Especialista (3) | Empleado (4) |
| --- | --- | --- | --- | --- |
| Inicio de sesión personal, perfil y cierre de sesión | Sí | Sí | Sí | Sí |
| Crear reportes | Sí | Sí | Sí | Sí |
| Consultar reportes | Todos | Todos | Propios y asignados, incluido historial | Solo propios |
| Asignar técnicos y avanzar estados de reportes | No | Sí | No | No |
| Recibir y marcar alertas de reportes | No | Sí | No | No |
| Consultar inventario y mapa | Sí | Sí | Sí | No |
| Alta, edición y baja lógica de activos | Sí | No | No | No |
| Baja física de activo sin historial, según validación del servicio | No | Sí | No | No |
| Registrar mantenimiento y actualizar consumibles | Sí | Sí | Sí | No |
| Ver agenda y completar tareas | Todas | Todas | Solo asignadas a su cuenta | No |
| Programar tareas y elegir técnico activo | Sí | Sí | No | No |
| Estadísticas, presupuesto, auditoría e infraestructura | Sí | Sí | No | No |
| Directorio de usuarios y roles | Sí | No | No | No |

La jerarquía no concede automáticamente todas las acciones de los niveles inferiores. La asignación y el seguimiento de reportes siguen siendo responsabilidad exclusiva del jefe técnico. No se modificaron las especialidades registradas. El modelo actual no define una relación entre especialidad y categoría de activo; los especialistas comparten el inventario técnico.

Los empleados disponen de un catálogo mínimo de código, nombre y ubicación para seleccionar el activo al reportar una falla. No reciben las fichas completas, el directorio de personas, costos ni auditoría.

## Autenticación

- Cada cuenta utiliza su correo existente como identificador y una contraseña individual basada en el nombre del correo más un sufijo aleatorio.
- La base de datos guarda hashes de contraseñas. Las contraseñas legibles se entregan únicamente en un archivo privado fuera de `public/`.
- Se eliminaron el selector de usuario, los botones de acceso de demostración y la ruta de sustitución de identidad.
- El servidor obtiene la identidad de la sesión autenticada. No acepta `X-User-Id`, la antigua variable de sesión `current_user_id` ni identidades de `localStorage`.
- El inicio de sesión regenera la sesión y exige una cuenta activa. Hay límites de 5 intentos por minuto por correo/IP y 30 por IP.
- Las operaciones con sesión requieren protección CSRF. El cierre de sesión invalida la sesión y regenera su token CSRF.
- La interfaz muestra solo módulos autorizados; rutas, controladores y políticas vuelven a comprobar los permisos en el servidor.
- La carga de datos depende del servidor y no recurre a datos de demostración si una petición falla.

## Entrega y renovación de credenciales

El comando `php artisan accounts:issue-credentials RUTA_PRIVADA_NUEVA.md` renueva las contraseñas de todas las cuentas existentes y revoca sesiones y tokens de «Recordarme». Requiere una carpeta existente fuera de `public/` y no sobrescribe archivos. No ejecutarlo para consultar credenciales: volver a ejecutarlo las cambia.

La entrega de esta tarea está en `output/credenciales/usuarios-y-contrasenas-20261021.md`, con permisos de lectura/escritura solo para el propietario. La carpeta y los respaldos están excluidos del versionado.

Estos correos son identificadores de la aplicación; esta tarea no crea buzones de correo electrónico.
