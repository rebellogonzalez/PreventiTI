# Accesos y roles de PreventiTI

Actualizado el 11 de noviembre de 2026. El catálogo inicial contiene 3 administradores, 1 jefe técnico, 6 técnicos especialistas y 30 empleados.

## Jerarquía y responsabilidades

| Operación | Administrador (1) | Jefe técnico (2) | Especialista (3) | Empleado (4) |
| --- | --- | --- | --- | --- |
| Inicio de sesión personal, perfil y cierre de sesión | Sí | Sí | Sí | Sí |
| Crear reportes | Sí | Sí | Sí | Sí |
| Consultar reportes | Todos | Todos | Propios y asignados, incluido historial | Solo propios |
| Asignar o reasignar técnicos e iniciar reportes recibidos | No | Sí | No | No |
| Registrar diagnóstico y finalizar reportes | No | Sí | Solo con asignación vigente | No |
| Recibir y marcar alertas de reportes | No | Sí | No | No |
| Consultar inventario, histórico de dispositivos y mapa | Sí | Sí | Sí | No |
| Alta, baja y reincorporación de dispositivos | Sí | Sí | No | No |
| Editar datos y coordenadas de un dispositivo | Sí | No | No | No |
| Registrar mantenimiento y actualizar consumibles | Sí | Sí | Sí | No |
| Ver agenda y completar tareas | Todas | Todas | Solo asignadas a su cuenta | No |
| Programar tareas y elegir técnico activo | Sí | Sí | No | No |
| Estadísticas, presupuesto, auditoría e infraestructura | Sí | Sí | No | No |
| Directorio, alta y baja de cuentas de los cuatro roles | Sí | Sí | No | No |

La jerarquía no concede automáticamente todas las acciones de los niveles inferiores. Desde **Reportes → Gestionar**, el especialista asignado puede diagnosticar o finalizar un reporte en curso y cerrar uno en diagnóstico. Al reasignarlo, ese permiso pasa al nuevo responsable. No se modificaron las especialidades registradas. El modelo actual no define una relación entre especialidad y categoría de activo; los especialistas comparten el inventario técnico.

Al crear un reporte, los empleados disponen de un catálogo mínimo con código, nombre, categoría y ubicación. Pueden seleccionar dispositivos de Operaciones A y B, Atención al cliente y Perímetro y accesos. El servidor comprueba el piso y la sala del equipo al listar los dispositivos y al guardar el reporte. Los administradores, el jefe técnico y los especialistas pueden seleccionar dispositivos de todas las zonas.

## Autenticación

- Cada cuenta utiliza su correo como identificador. En **Usuarios y roles → Agregar usuario**, el responsable establece la contraseña inicial y el rol. Las cuentas técnicas también requieren una especialidad. La base de datos guarda el hash de la contraseña.
- **Dar de baja** deshabilita la cuenta, revoca su acceso y conserva su historial. La gestión de la propia baja corresponde a otro administrador o jefe técnico. Las altas y bajas quedan registradas en auditoría.
- La baja deja disponibles los dispositivos a cargo de la cuenta, sus tareas pendientes y sus asignaciones técnicas en reportes abiertos. En **Calendario → Pendientes de asignación** se elige un nuevo responsable. El jefe técnico puede filtrar los reportes por **Sin asignar** y continuar desde el estado alcanzado.
- Si se da de baja al jefe técnico, los reportes abiertos pasan al siguiente jefe activo por orden de alta. Si la jefatura queda vacante, los recibe el próximo jefe que se incorpore. Las tareas completadas, reportes cerrados y autorías conservan sus responsables históricos.
- Se eliminaron el selector de usuario, los botones de acceso de demostración y la ruta de sustitución de identidad.
- El servidor obtiene la identidad de la sesión autenticada. No acepta `X-User-Id`, la antigua variable de sesión `current_user_id` ni identidades de `localStorage`.
- El inicio de sesión regenera la sesión y exige una cuenta activa. Hay límites de 5 intentos por minuto por correo/IP y 30 por IP.
- Las operaciones con sesión requieren protección CSRF. El cierre de sesión invalida la sesión y regenera su token CSRF.
- La interfaz muestra solo módulos autorizados; rutas, controladores y políticas vuelven a comprobar los permisos en el servidor.
- La carga de datos depende del servidor y no recurre a datos de demostración si una petición falla.

## Entrega y renovación de credenciales

El comando `php artisan accounts:issue-credentials RUTA_PRIVADA_NUEVA.md` renueva las contraseñas de las cuentas activas y revoca sesiones y tokens de «Recordarme». Requiere una carpeta existente fuera de `public/` y no sobrescribe archivos. No ejecutarlo para consultar credenciales: volver a ejecutarlo las cambia.

La entrega de esta tarea está en `output/credenciales/usuarios-y-contrasenas-20261021.md`, con permisos de lectura/escritura solo para el propietario. La carpeta y los respaldos están excluidos del versionado.

Estos correos son identificadores de la aplicación; esta tarea no crea buzones de correo electrónico.
