# PreventiTI — Gestión Preventiva de Activos Tecnológicos

Sistema web integral desarrollado en **Laravel 13 (PHP 8.3+)** para el monitoreo patrimonial, administración del ciclo de vida de incidencias operativas y mantenimiento preventivo de infraestructura tecnológica.

---

## 👥 Equipo y Responsabilidades
- **Ramiro Rebello:** Coordinador General & Líder de Desarrollo Web (Arquitectura Laravel, Controladores, Capa de Servicios, API REST, Integración Frontend).
- **Daniel Sanz:** Especialista en Datos & Lógica de Negocio (Modelado relacional 3FN, DDL MariaDB 10.11+, Seeders, Índices B-Tree, DRP).
- **Ignacio "Nacho" de la Rosa:** Especialista en Infraestructura, Redes y Ciberseguridad (Topología 2 VLANs, pfSense 2 Reglas, VPN WireGuard, Ubuntu Server 22.04 LTS, Windows Server AD DS).

---

## 📁 Estructura del Repositorio

```text
├── app/                  # Modelos, servicios, controladores, validaciones y políticas
├── bootstrap/            # Inicialización y registro de middleware
├── config/               # Configuración de Laravel y del mapa
├── database/             # Migraciones, seeders, datos de origen, SQLite y respaldos
├── docs/                 # Índice en docs/README.md
│   ├── arquitectura/     # Memoria técnica, mapa, red, flujo y diagramas editables
│   ├── guias/            # Frontend, accesos y migración
│   ├── planificacion/    # Alcance, hitos y backlog
│   ├── referencias/      # Documentos originales y extracciones de texto
│   ├── presentaciones/   # PDF y fuente Marp
│   ├── historico/        # Guía del antiguo prototipo
│   └── auditoria/        # Evidencia del esquema local
├── infrastructure/       # Configuraciones de despliegue, DDL y scripts operativos
├── public/               # Única raíz que debe servir el servidor web
│   ├── css/              # Estilos propios y Tailwind compilado localmente
│   └── js/               # Entrada app.js, core/, features/, lib/, ui/, floorplan/ y vendor/
├── resources/views/      # Vista principal, autenticación, parciales y modales
├── routes/               # Rutas web, API y consola
├── scripts/docs/         # Generación auxiliar de documentos
├── storage/              # Archivos de la aplicación, logs, sesiones y cachés
├── output/               # Entregables, previsualizaciones y archivos privados locales
├── tests/                # Pruebas de backend (Feature/) y JavaScript (Frontend/)
├── index.html            # Redirección de compatibilidad a la aplicación
├── artisan               # CLI de Laravel
└── composer.json         # Dependencias PHP y comandos de desarrollo
```

Consultar [la estructura y los criterios de mantenimiento](docs/ESTRUCTURA.md) y [el índice documental](docs/README.md). El JavaScript propio se edita en `public/`. Tailwind y las bibliotecas visuales se generan localmente con `npm run build`; sus archivos ya están incluidos en `public/`, por lo que la aplicación funciona sin acceso a CDN. Ver [guía frontend](docs/guias/FRONTEND.md).

La instalación local utiliza **MariaDB 10.11.19**, con los datos originales preservados y reglas de negocio en la base. Configuración privada, operación del servicio, pruebas, respaldos y reversión a SQLite: [guía de integración MariaDB](infrastructure/database/db_connection_guide.md). Los datos y las credenciales quedan fuera de Git.

Entrega para Ubuntu y redes: [archivos exactos para Nacho y orden de instalación](ENTREGA_NACHO.md). Incluye plantillas Linux y verificación del respaldo; la entrega privada se distribuye por separado.

---

## 🚀 Puesta en Marcha en Desarrollo

### 1. Iniciar Servidor Web Laravel
```bash
php artisan serve
```
Abrir en el navegador: `http://127.0.0.1:8000`

### 2. Ejecutar Pruebas Automatizadas
```bash
php artisan test
node --test tests/Frontend/*.test.mjs
```

El mapa operativo 3D está disponible en `/#mapa`. Utiliza Three.js local, inventario real y coordenadas configurables independientes. Después de actualizar, ejecutar `php artisan migrate`. Arquitectura, permisos y posicionamiento: [Floorplan 3D](docs/arquitectura/FLOORPLAN_3D.md). Pruebas del adaptador visual: `node --test tests/Frontend/floorplan.test.mjs`.

### 3. Cuentas y permisos
La interfaz requiere el servidor Laravel y una sesión personal con correo y contraseña. Las 40 cuentas tienen contraseñas individuales; se eliminaron los accesos rápidos y el selector para simular usuarios.

La jerarquía y la matriz de accesos están documentadas en [Accesos y roles](docs/guias/ACCESOS_Y_ROLES.md). El listado privado de credenciales se entrega fuera de `public/` y no forma parte de la interfaz.

---

## 🔒 Red de laboratorio aprobada

- **2 VLANs:** VLAN 10 Clientes/POS (`192.168.10.0/24`) y VLAN 20 Servidores (`192.168.20.0/24`).
- **1 Firewall Virtual:** pfSense CE 2.7.
- **EXACTAMENTE 2 Reglas de Filtrado entre VLAN 10 y VLAN 20:**
  1. *PASS:* Tráfico web HTTPS (puerto 443 TCP) hacia `192.168.20.10`.
  2. *BLOCK:* Denegación de SSH (puerto 22) y MySQL (puerto 3306) desde VLAN 10.
- **Acceso de usuarios:** se utiliza directamente `https://preventiti.local`; no se depende de la redirección HTTP desde VLAN 10. El acceso por IP solo corresponde si el certificado TLS incluye `192.168.20.10` entre sus nombres válidos.
- **DHCP VLAN 10:** rango `192.168.10.50-192.168.10.200` y gateway `192.168.10.1`. El DNS debe conservar el valor utilizado por el laboratorio aprobado; el repositorio no documenta una IP de DNS para los clientes.
- **1 VPN Administrativa:** WireGuard (`10.10.0.0/24`, UDP 51820) para administración exclusiva del Técnico Líder.

Las dos reglas enumeradas son la política inter-VLAN evaluada. Los controles operativos de WAN y de la interfaz WireGuard que permiten establecer y utilizar el túnel se documentan por separado y no modifican esas dos reglas ni el direccionamiento aprobado.
