# PreventiTI — Guía de Conexión de Base de Datos para Daniel Sanz

**Autor:** Ramiro Rebello (Líder de Desarrollo Web)  
**Destinatario:** Daniel Sanz (Especialista en Datos & Lógica de Negocio)  
**Estado:** Backend listo para acoplar MariaDB 10.11+ / MySQL 8.0

---

## 1. Contexto Actual
Para el desarrollo de la aplicación y la verificación de tests en local, el backend de Laravel se encuentra operando sobre **SQLite** (`database/database.sqlite`), con migraciones y modelos 100% compatibles con el estándar relacional SQL.

El DDL físico exacto para MariaDB 10.11+ (con tipos de datos, índices compuestos y constraints InnoDB) se encuentra listo en:
`infrastructure/database/mariadb_schema.sql`

---

## 2. Pasos para Conectar la Base de Datos Real en MariaDB

Cuando tengas levantado el servidor MariaDB (sea en la VM de Ubuntu `192.168.20.10` o en un contenedor/servidor local):

### Paso 1: Crear la Base de Datos y Usuario en MariaDB
```sql
CREATE DATABASE preventiti CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'preventiti_user'@'%' IDENTIFIED BY 'preventiti_secure_2026';
GRANT ALL PRIVILEGES ON preventiti.* TO 'preventiti_user'@'%';
FLUSH PRIVILEGES;
```

### Paso 2: Configurar las Variables en `.env`
Editar el archivo `.env` en la raíz del proyecto reemplazando la sección de base de datos:

```dotenv
DB_CONNECTION=mysql
DB_HOST=192.168.20.10   # o 127.0.0.1 si es local
DB_PORT=3306
DB_DATABASE=preventiti
DB_USERNAME=preventiti_user
DB_PASSWORD=preventiti_secure_2026
```

### Paso 3: Ejecutar las Migraciones y Seeders Oficiales
Desde la terminal del proyecto:
```bash
php artisan migrate:fresh --seed
```
Esto creará automáticamente las 11 tablas e insertará los:
- 4 roles RBAC
- 10 categorías de activos
- 8 ubicaciones físicas
- 38 usuarios documentados
- 78 activos patrimoniales
- Reportes de muestra y notificaciones persistentes para administración y liderazgo técnico
- Mantenimientos y calendario

### Paso 4: Comprobar la Conexión
```bash
php artisan test --filter=PreventiTITest
```
Si todos los tests pasan en verde, la base de datos de producción estará 100% enlazada con la aplicación sin requerir ningún cambio en el código PHP.
