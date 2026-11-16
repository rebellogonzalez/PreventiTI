# Verificacion de instalacion Ubuntu

Esta guia complementa [ENTREGA_NACHO.md](../../ENTREGA_NACHO.md). Los requisitos se derivan del codigo y las dependencias del repositorio. Registrar fechas, resultados y evidencias del entorno real en el archivo privado del equipo.

## Requisitos fijados por dependencias

Aunque `composer.json` admite PHP >=8.3, los paquetes Symfony 8.1 de `composer.lock` requieren **PHP >=8.4.1**. Instalar CLI y FPM compatibles y conservar el archivo de bloqueo.

Ejecutar como cuenta de despliegue desde el directorio de la aplicacion:

```sh
php -v
composer install --no-dev --optimize-autoloader --no-interaction
composer check-platform-reqs --no-dev
php -r 'exit(extension_loaded("pdo_mysql") && extension_loaded("zip") ? 0 : 1);'
```

La comprobacion CLI no acredita por si sola la version y extensiones del proceso FPM que atiende solicitudes web; verificar ambas.

## Directorios y permisos

Un archivo de codigo generado por Git puede omitir directorios vacios. Prepararlos antes de generar caches:

```sh
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
```

Seguir el script `infrastructure/scripts/usuarios_permisos.sh` y utilizar la cuenta administrativa existente de despliegue. Crear caches como esa cuenta, desde el directorio de la aplicacion, y reaplicar los permisos despues:

```sh
php artisan config:clear
php artisan config:cache
php artisan view:cache
sudo env DEPLOY_USER=CUENTA_ADMINISTRADORA_EXISTENTE bash infrastructure/scripts/usuarios_permisos.sh
sudo -u www-data php artisan about
```

Sustituir `CUENTA_ADMINISTRADORA_EXISTENTE` por la cuenta real. Una umask privada puede crear caches nuevas ilegibles para el grupo del servicio; por eso el ajuste debe hacerse despues de generarlas. La cuenta web debe leer el codigo y escribir unicamente en los directorios de ejecucion autorizados.

Comprobar la sintaxis del servidor web y la configuracion PHP antes de cambiar el servicio activo. La respuesta HTTP debe comprobarse despues de reiniciar los procesos con sus grupos efectivos actualizados.

## Criterios de aceptacion

| Area | Caso a ejecutar | Evidencia de cierre |
| --- | --- | --- |
| Programacion | Login y logout con cuentas existentes; rechazos de credenciales invalidas y accesos fuera del rol | Resultado esperado y obtenido por rol |
| Programacion / BD | Inventario y baja logica; reporte, asignacion, diagnostico y cierre; mantenimiento, consumibles, alertas, filtros y auditoria | Persistencia y trazabilidad de cada flujo |
| BD | Integridad, reglas y rechazos en una base de prueba; respaldo desde la interfaz y restauracion separada | Comparacion de datos, estructura y archivos |
| POSIX | Servicios, permisos efectivos, tareas programadas, logs, respaldo y recuperacion | Ejecucion como las cuentas previstas y evidencia posterior a reinicio |
| Conectividad | Acceso desde el cliente real por las rutas previstas; reglas y denegaciones de gestion | Origen, destino, resultado y configuracion activa |
| Seguridad | HTTPS confiable desde cada cliente previsto y procedimiento de renovacion | Validacion nativa sin excepciones |
| Microsoft | Servicios y politicas del dominio requeridos por los hitos; resolucion del nombre de la aplicacion | Comprobacion desde clientes del dominio |
| VPN | Conexion del usuario autorizado, handshake y acceso permitido por el tunel | Identificacion del origen VPN y permisos efectivos |
| Todas | Ensayo de recuperacion y aceptacion final del conjunto | Matriz firmada por responsables y evidencia revisada |

Una respuesta de la pagina de ingreso no acredita autenticacion, permisos ni flujos completos. Una prueba desde el servidor tampoco sustituye las realizadas desde un cliente o a traves de la VPN.

## Datos y pruebas aisladas

Las pruebas que reconstruyen tablas o ejecutan casos de escritura deben utilizar la copia y las bases de prueba indicadas en la guia de entrega. Verificar sus destinos antes de ejecutarlas.

El manifiesto original acredita la restauracion inicial antes de trafico. Tras usar la aplicacion pueden aparecer sesiones, auditorias y otros registros nuevos. Conservar esos cambios; no reimportar la base original para hacer coincidir conteos.

Crear un respaldo nuevo desde el flujo normal, verificarlo y restaurarlo en un destino aislado. Los archivos de usuario, el esquema y la logica SQL deben formar parte de la comprobacion prevista.

## Preparacion de la entrega final

Antes de etiquetar una version final:

1. Registrar el commit realmente desplegado, las versiones y las migraciones aplicadas.
2. Completar los criterios de aceptacion y resolver o declarar los defectos restantes.
3. Actualizar la topologia y procedimientos con la configuracion activa; guardar los detalles internos y evidencias privadas con su acceso correspondiente.
4. Incorporar los scripts de operacion revisados y documentar sus entradas y cuentas.
5. Preparar el paquete de codigo y, por separado, la entrega privada de datos y configuracion.
6. Ensayar instalacion o actualizacion, respaldo y recuperacion.
7. Registrar el corte de datos y evitar dos instalaciones con escrituras simultaneas.

La etiqueta de version final debe corresponder al conjunto aceptado. La existencia de esta lista no acredita que las comprobaciones ya se hayan realizado.
