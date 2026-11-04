# Estructura y mantenimiento

Organización actualizada el 21 de octubre de 2026.

## Código de la aplicación

- `app/Http/Controllers/Api/`: entrada y respuesta de los endpoints.
- `app/Http/Requests/`, `app/Http/Middleware/` y `app/Policies/`: validaciones, sesión y autorización.
- `app/Services/`: reglas y operaciones de negocio; `app/Models/`: acceso a datos y relaciones.
- `app/Console/Commands/`, `app/Support/` y `app/Providers/`: consola, utilidades y registro de servicios.
- `database/migrations/`, `database/seeders/`, `database/factories/` y `database/data/`: evolución del esquema y datos iniciales. Las migraciones históricas son necesarias para reconstruir una instalación; se conservan completas.
- `routes/` y `config/`: rutas y configuración según las convenciones de Laravel.

## Interfaz

`public/js/app.js` es el punto de entrada ES module. Importa la sesión, el cliente API y los selectores; crea las vistas y conecta navegación, permisos y carga inicial.

| Carpeta | Responsabilidad |
| --- | --- |
| `public/js/core/` | Estado inicial de sesión y comunicación con la API |
| `public/js/features/` | Dashboard, calendario, estadísticas, inventario, reportes, mantenimiento, consumibles, usuarios e infraestructura |
| `public/js/lib/` | Selección y filtrado de inventario, historial de reportes y cálculo del historial por técnico |
| `public/js/ui/` | Avisos, modales, formularios y sus selectores |
| `public/js/floorplan/` | Mapa 3D; carga diferida desde el router |
| `public/js/vendor/three/` | Three.js, OrbitControls y licencia original |
| `public/css/` | Estilos compartidos y del mapa |
| `resources/views/app/partials/` | Navegación lateral y cabecera |
| `resources/views/app/modals/` | Un archivo Blade por formulario o ficha |
| `resources/views/auth/` | Pantalla de acceso |

Cada función `create…` recibe únicamente las dependencias que necesita y mantiene sus filtros dentro de su módulo. El objeto `db` se comparte por referencia: la sincronización API actualiza su contenido conservando su identidad. Mantener ese contrato al modificar el cliente de sesión. Los manejadores usados en atributos HTML siguen expuestos en `window`.

Editar los recursos directamente en `public/`. No copiar nuevamente `js/` o `css/` a la raíz. La vista principal importa un solo script de entrada con `type="module"`; Node se utiliza para las pruebas, sin instalación de paquetes frontend.

## Documentos y archivos locales

El [índice documental](README.md) agrupa guías, arquitectura, planificación, fuentes y antecedentes. `scripts/docs/` contiene el generador auxiliar que antes estaba en `tmp/`. Las imágenes de revisión se conservan en `output/previews/pdfs/`.

Se conservan `.env`, la base activa, todos sus respaldos, datos semilla, credenciales, entregables, capturas, `.venv`, dependencias instaladas, licencias y archivos de infraestructura. La presentación Marp y el generador PDF aún hacen referencia a insumos externos del autor; no se borraron ni se regeneraron esos entregables. `index.html` y `public/index.html` siguen disponibles como redirecciones de compatibilidad.

## Limpieza del 21/10/2026

Se retiraron las 15 copias idénticas de archivos que existían en `js/` y `css/`, incluyendo la copia duplicada de Three.js. También se retiraron `public/js/mock-data.js` (redirección sin consumidores), la vista inicial `welcome.blade.php` y sus recursos Vite sin uso, dos archivos `.DS_Store` y la prueba de ejemplo que solo afirmaba `true`. La prueba de acceso a `/login` se conserva con el nombre `LoginPageTest`.

En esa limpieza se eliminaron las llamadas a npm de `composer setup`. **Actualización del 22/10/2026:** ahora existe un proyecto npm con versiones fijadas para generar Tailwind y copiar bibliotecas y fuentes locales mediante `npm run build`. Los recursos generados se incluyen en `public/`; ejecutar la aplicación no requiere Node ni acceso a CDN. Se conservan las dependencias PHP y su archivo de versiones. Ver [guía frontend](guias/FRONTEND.md).

Antes de los cambios se guardó `output/backups/organizacion-20261021-112140/archivos-previos.tar.gz`. Incluye las versiones anteriores de los archivos reorganizados. Su manifiesto `integridad-datos.json` registra hashes de `.env`, los archivos de `database/` y los entregables privados existentes. El respaldo es privado y está excluido del versionado; para recuperar un archivo, extraerlo en una carpeta aparte y comparar antes de reemplazarlo.

## Comprobaciones

```bash
php artisan test
node --test tests/Frontend/*.test.mjs
composer validate --no-check-publish
```

PHPUnit usa SQLite en memoria según `phpunit.xml`. La reorganización no requiere migraciones, regeneración de contraseñas ni cambios en la base de desarrollo.

Resultado de la revisión del 21/10/2026:

- 35 pruebas PHP aprobadas, con 310 aserciones, y 14 pruebas JavaScript aprobadas. El total PHP anterior era 36; la única prueba retirada afirmaba `true` sin comprobar funcionalidad.
- Composer válido y sintaxis de JavaScript y del generador Python correctas.
- Las 10 vistas reorganizadas produjeron el mismo HTML que la versión respaldada en una comparación con DOM simulado. También se comprobaron sincronización de estado, restricciones de navegación y carga/liberación del mapa mediante un sustituto del renderizador. Esta comprobación no sustituye una revisión visual de WebGL en navegador.
- El HTML Blade se conserva al expandir los parciales, salvo la carga del módulo de entrada, las rutas de estilos y el comentario actualizado de modales.
- Los 35 archivos del manifiesto privado conservan sus hashes; también se verificó la preservación de 24 documentos, diagramas, CSV, extracciones y previsualizaciones. Los 72 archivos revisados de backend, rutas, configuración e infraestructura no cambiaron.
- Enlaces documentales e imports locales sin destinos faltantes.
