# Entrega de PreventiTI a Nacho: Ubuntu y redes

Esta versión incluye la integración funcional de MariaDB 10.11.x y la preparación para desplegar en Ubuntu `192.168.20.10`. La aplicación está verificada en el Mac de desarrollo; los servicios, certificados y conectividad de la VM deben comprobarse en destino. No se ha desplegado ni modificado la infraestructura de Nacho.

## Qué enviar

Enviar el **directorio privado completo** generado por `scripts/deployment/prepare_handoff.py`, o su ZIP de entrega, por un canal privado. Sus nombres internos son estables:

| Archivo | Contenido / uso |
| --- | --- |
| `01_codigo.tar.gz` | Código completo del commit indicado en `LEEME.txt`, incluidas migraciones, reglas SQL, frontend compilado y documentación. Extrae una carpeta `preventiti/`. |
| `02_respaldo.zip` | SQL de la MariaDB activa y archivos de `storage/app`, con manifiesto SHA-256. Contiene datos reales y hashes de cuentas. |
| `03_app-key.env` | Únicamente la `APP_KEY` original, necesaria para conservar los datos cifrados. Incorporarla al `.env` de Ubuntu. |
| `04_estado_base.json` | Conteos y huellas de todas las tablas, columnas, índices, relaciones, triggers y procedimientos. Comparar antes de abrir tráfico. |
| `05_validacion.json` | Commit, fecha y resultado de restaurar ESTE respaldo en una base temporal nueva. |
| `SHA256SUMS` | Verificación de todos los archivos de la entrega. |
| `LEEME.txt` | Commit, rama y listado de entrega. |

También se puede obtener el código desde Git, utilizando el **mismo commit** que el respaldo. El SQL, la clave y los archivos privados no están en GitHub. No enviar el `.env` del Mac, sus contraseñas administrativas, `runtime/datos`, los binarios macOS, `launchd`, `.venv`, `node_modules` ni `vendor`. Las dependencias PHP se instalan en Ubuntu desde `composer.lock`.

Los archivos públicos específicos que Nacho necesita, todos incluidos en el código del commit, son:

| Ruta en el repositorio | Función |
| --- | --- |
| `ENTREGA_NACHO.md` | Orden de instalación y aceptación |
| `infrastructure/configs/nginx_preventiti.conf` | Nginx HTTPS, raíz `public/`, PHP-FPM 8.4 |
| `infrastructure/configs/.env.ubuntu.example` | Entorno Linux sin secretos |
| `infrastructure/configs/mariadb_preventiti.cnf` | MariaDB local por socket, UTC y UTF-8 |
| `infrastructure/configs/backup.cnf.example` | Conexión privada del lector de respaldos |
| `infrastructure/database/usuarios_ubuntu.sql.example` | Base y cuentas nuevas con permisos separados |
| `infrastructure/configs/pfsense_rules.md` | Las dos reglas inter-VLAN aprobadas |
| `infrastructure/configs/wireguard_wg0.conf.example` | Referencia de WireGuard sin claves reales |
| `infrastructure/scripts/usuarios_permisos.sh` | Código propiedad del administrador; escritura web limitada |
| `infrastructure/scripts/healthcheck.sh` | Comprobación real de servicios Linux |
| `infrastructure/scripts/respaldo_preventiti.sh` | Entrada para respaldos mediante Laravel |
| `scripts/database/backup.php` | Respaldo SQL y archivos con verificación |
| `scripts/deployment/database_manifest.php` | Comparación de la restauración contra la entrega |
| `database/migrations/` y `database/sql/mariadb_rules.sql` | Esquema vigente y seis reglas de base de datos |
| `docs/arquitectura/TOPOLOGIA_RED_PREVENTITI.md` | Direccionamiento y responsabilidades de red |
| `infrastructure/database/db_connection_guide.md` | Reglas, respaldo y reversión del entorno original |

## Preparación del servidor

Nacho debe instalar **PHP 8.4 CLI y FPM**, Composer, Nginx y **MariaDB 10.11.x**; utilizar cliente y servidor 10.11.19 reproduce las versiones de la copia verificada. Laravel exige PHP >=8.3; esta entrega configura el socket FPM 8.4. No reutilizar los antiguos scripts con PHP 8.2. Revisar versiones disponibles en los repositorios autorizados del servidor antes de instalar.

PHP necesita las extensiones exigidas por Composer y, para esta aplicación, `pdo_mysql` y `zip`. `composer check-platform-reqs --no-dev` comprueba los requisitos de dependencias; verificar `zip` por separado. Los recursos frontend ya están en `public/`; Node solo se necesita para reconstruirlos o ejecutar sus pruebas.

1. Guardar la entrega fuera de la raíz web, en un directorio privado. Verificar `sha256sum -c SHA256SUMS`. Comprobar que `/var/www/preventiti` no contiene otro despliegue que pueda sobrescribirse.
2. Extraer `01_codigo.tar.gz` en `/var/www` y entrar en `/var/www/preventiti`.
3. Ejecutar como administrador, reemplazando `nacho` si la cuenta real tiene otro nombre:

   ```sh
   sudo env DEPLOY_USER=nacho bash infrastructure/scripts/usuarios_permisos.sh
   ```

   La cuenta administrativa conserva el código. `www-data` recibe lectura y escritura únicamente en `storage` y `bootstrap/cache`. No aplicar `chmod 777`. Reiniciar FPM después de cambiar sus grupos.
4. Como cuenta de despliegue, ejecutar:

   ```sh
   composer install --no-dev --optimize-autoloader --no-interaction
   composer check-platform-reqs --no-dev
   php -r 'exit(extension_loaded("pdo_mysql") && extension_loaded("zip") ? 0 : 1);'
   ```

## Base y configuración privada

1. Copiar `mariadb_preventiti.cnf` en `/etc/mysql/mariadb.conf.d/60-preventiti.cnf`, comprobar que el socket del servidor es `/run/mysqld/mysqld.sock` y reiniciar MariaDB con systemd. La aplicación y la base deben residir en el mismo Ubuntu; TCP queda deshabilitado.
2. Comprobar con `sudo mariadb` la versión 10.11.x y la cuenta administrativa `root@localhost`. El volcado incluye ese `DEFINER`: debe existir y poder ejecutar la lógica. No cambiarlo mediante reemplazos globales sobre el SQL con datos. Para utilizar otro propietario se requiere preparar y verificar una conversión específica.
3. Copiar `usuarios_ubuntu.sql.example` a una ubicación privada, sustituir AMBAS contraseñas por valores nuevos distintos y ejecutarlo sin `--force` mediante `sudo mariadb`. Se detiene si ya existe la base o una cuenta. No sobrescribir una instalación existente. El usuario web no tiene DDL; el lector de respaldos no tiene escritura.
4. Extraer `02_respaldo.zip` en el directorio privado. Restaurar en la base recién creada:

   ```sh
   sudo mariadb --binary-mode --comments preventiti < /ruta/privada/database/database.sql
   ```

   `--comments` conserva las definiciones exactas de los triggers para que sus huellas coincidan. Recuperar el contenido de `storage/app/` del ZIP dentro de `/var/www/preventiti/storage/app/`, conservando archivos y subdirectorios. Reaplicar el script de permisos después de copiarlos.
5. Crear `.env` a partir de `infrastructure/configs/.env.ubuntu.example`. Incorporar la `APP_KEY` de `03_app-key.env` y la NUEVA contraseña de `preventiti_app`. Mantener `APP_ENV=production`, `APP_DEBUG=false` y `APP_URL=https://preventiti.local`. No ejecutar `key:generate`.
6. Crear `/etc/preventiti/backup.cnf` desde su ejemplo, con la nueva contraseña de `preventiti_backup`. Permitir lectura al grupo `app_preventiti`, sin acceso para otros usuarios. Preparar las carpetas:

   ```sh
   sudo install -d -o root -g app_preventiti -m 750 /etc/preventiti
   sudo chown root:app_preventiti /etc/preventiti/backup.cnf
   sudo chmod 640 /etc/preventiti/backup.cnf
   sudo install -d -o www-data -g app_preventiti -m 700 /var/backups/preventiti
   ```

   La ruta del ejemplo es `/usr/bin/mariadb-dump`; verificarla con `command -v mariadb-dump`. Las contraseñas del servidor del Mac no se trasladan.
7. Antes de cachear la configuración o permitir tráfico, comprobar la restauración como administrador. En Ubuntu con autenticación administrativa por socket:

   ```sh
   sudo env DB_USERNAME=root DB_PASSWORD= DB_URL= php scripts/deployment/database_manifest.php --expected /ruta/privada/04_estado_base.json
   ```

   Debe devolver `verified: true`. Compara contenido de todas las tablas y esquema, no solo conteos. La versión menor del servidor puede variar dentro de 10.11.x; las huellas deben coincidir. Si hay configuración cacheada, limpiarla antes de esta comprobación con `php artisan config:clear`.

El respaldo ya contiene las 27 migraciones aplicadas, 32 tablas, 10 triggers y 3 procedimientos. **No ejecutar `migrate:fresh`, `db:wipe`, seeders ni reinstalar `mariadb_rules.sql` encima de la restauración.** Consultar `php artisan migrate:status`. Migraciones futuras requieren respaldo y una cuenta administrativa; no conceder DDL al usuario web para resolver ese trámite.

## Nginx, HTTPS y red

- Adaptar y habilitar `nginx_preventiti.conf`; verificar la existencia del socket FPM `/run/php/php8.4-fpm.sock`.
- La raíz web debe ser `/var/www/preventiti/public`, nunca la raíz del repositorio. La plantilla solo ejecuta `public/index.php` como PHP.
- Instalar certificado y clave en las rutas indicadas por Nginx. El certificado debe cubrir `preventiti.local` y ser confiable para los clientes. Si se usa la IP en el navegador, el certificado también debe incluir esa IP.
- Configurar el DNS ya utilizado por el laboratorio para que `preventiti.local` resuelva a `192.168.20.10`. La entrega no inventa una IP de servidor DNS ni datos WAN.
- Conservar VLAN 10 (`192.168.10.0/24`), VLAN 20 (`192.168.20.0/24`) y las dos reglas: permitir TCP/443 a Ubuntu; bloquear TCP/22 y TCP/3306 desde VLAN 10 hacia VLAN 20. Acceder directamente por HTTPS; no depender de que VLAN 10 pueda llegar al puerto 80.
- Configurar el túnel administrativo WireGuard aprobado con claves reales y verificar handshake y acceso autorizado. El `.conf.example` es una referencia, no un archivo importable de pfSense. No se incluyen claves reales de VPN.
- Windows Server/AD continúa como componente de la red; el traslado conserva las cuentas propias de PreventiTI y no incorpora autenticación LDAP/AD.

Antes de publicar, ejecutar `sudo nginx -t`. Una vez ajustados los permisos y la configuración privada:

```sh
php artisan config:cache
php artisan view:cache
sudo systemctl restart php8.4-fpm nginx
sudo systemctl enable nginx php8.4-fpm mariadb
```

Utilizar Nginx/FPM para el servicio desplegado. Los lanzadores `local_mariadb.py`, `switch_connection.py` y `launchd` pertenecen al Mac; no son instaladores del servidor Ubuntu. Para respaldo manual o programado, ejecutar como `www-data` `bash infrastructure/scripts/respaldo_preventiti.sh`. El propietario de las copias debe seguir siendo la cuenta del servicio web.

## Aceptación con Nacho y corte final

La comparación del manifiesto se realiza ANTES de cualquier solicitud que cree sesiones o cambie caché. Después, comprobar:

- `https://preventiti.local/up` y login responden correctamente con certificado confiable.
- Una cuenta existente puede entrar; permisos, inventario, mapa, reportes y estadísticas conservan sus datos.
- En una base de pruebas, las seis reglas funcionan para casos válidos e inválidos; los tests MariaDB no deben utilizar `preventiti`. Para repetir los 100 tests se necesitan dependencias de desarrollo en una copia separada, cuentas de prueba y `phpunit.mariadb.xml` con sus conexiones privadas de Linux.
- La interfaz de Infraestructura genera y descarga un respaldo; se ensaya su restauración en una base nueva, incluyendo datos, triggers y rutinas.
- Desde VLAN 10 funciona HTTPS y no se accede a SSH ni MariaDB. La administración por VPN funciona y queda documentada con evidencias. El escaneo desde Ubuntu no sustituye esa prueba desde un cliente de VLAN 10.
- `bash infrastructure/scripts/healthcheck.sh` detecta servicios reales; repetir después de reiniciar Ubuntu.

Preparar un ensayo en Ubuntu permite que el Mac siga funcionando mientras Nacho valida la red. La entrega es una instantánea: si se producen cambios posteriores en el Mac, preparar otra entrega para el corte final. Durante ese corte detener escrituras del origen y no volver a abrirlo para uso simultáneo. Conservar el origen como respaldo. No hay sincronización automática y una reversión posterior requiere decidir cómo recuperar los datos nuevos del servidor.

## Regenerar la entrega en el Mac

Con todos los cambios commiteados y el árbol limpio:

```sh
python3 -B scripts/deployment/prepare_handoff.py --mariadb-home /Users/ramirorebello/PreventiTI-MariaDB-integracion-20261115 --out /ruta/privada/entrega-nueva
```

El comando exige un destino nuevo fuera del repositorio, activa mantenimiento brevemente, crea el respaldo y el manifiesto, restaura en una base aleatoria exclusiva, compara su contenido y la elimina. Finalmente restablece el servicio del Mac. No escribe en SQLite. No copia contraseñas del servidor del Mac ni publica la entrega. El directorio privado y su ZIP contienen datos reales y la `APP_KEY`; enviarlos por canal privado.

Referencias: [Laravel 13: despliegue](https://laravel.com/framework/docs/13.x/deployment), [MariaDB: mariadb-dump](https://mariadb.com/docs/server/clients-and-utilities/backup-restore-and-import-clients/mariadb-dump), [topología del proyecto](docs/arquitectura/TOPOLOGIA_RED_PREVENTITI.md).
