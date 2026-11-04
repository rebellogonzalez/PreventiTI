# Ejecutar y modificar la interfaz

La interfaz se sirve desde Laravel y requiere una sesión personal. Desde la raíz del proyecto, iniciar:

```bash
php artisan serve
```

Abrir `http://127.0.0.1:8000` e ingresar con las credenciales existentes. El servidor web debe tener `public/` como raíz pública. El proyecto requiere PHP 8.3+ y sus dependencias de Composer; la base y la configuración local existentes se conservan.

## Dónde editar

- `resources/views/app.blade.php`: estructura principal y carga del módulo de entrada.
- `resources/views/app/partials/`: cabecera y menú lateral.
- `resources/views/app/modals/`: formularios y ficha de activo.
- `public/js/features/`: vistas y comportamiento de cada área.
- `public/js/ui/`: formularios, modales y avisos compartidos.
- `public/js/core/api-client.js`: comunicación con Laravel y sincronización de los datos autorizados.
- `public/js/lib/`: filtros y selección de datos comprobados por las pruebas frontend.
- `public/js/floorplan/` y `public/css/map-styles.css`: mapa 3D.
- `public/css/styles.css`: estilos de la aplicación.

El navegador carga módulos ES nativos sin empaquetar el JavaScript de la aplicación. Los manejadores HTML siguen utilizando las funciones publicadas en `window`. La inicialización ocurre en `DOMContentLoaded`, después de cargar los módulos. El cliente API conserva la referencia compartida a `db` al sincronizar la sesión.

Tailwind se sirve como CSS compilado local. Lucide, Chart.js y las fuentes del login también se sirven desde `public/`; Three.js conserva su distribución local. La interfaz no necesita acceder a CDN ni Google Fonts. Las versiones están fijadas en `package.json` y `package-lock.json`; las licencias acompañan a los recursos y `public/vendor-manifest.json` registra sus SHA-256.

Para regenerar estilos y recursos al introducir clases Tailwind nuevas o actualizar una biblioteca:

```bash
npm ci --ignore-scripts
npm run build
```

La descarga solo ocurre al instalar dependencias de desarrollo. El servidor y los navegadores utilizan los archivos ya generados y no necesitan Node ni acceso a Internet. `tailwind.config.cjs` examina las vistas y módulos; las clases deben escribirse completas para que el compilador las detecte. Las pantallas y acciones disponibles dependen de los [permisos de la cuenta](ACCESOS_Y_ROLES.md).

## Alta y edición de activos

El administrador dispone de **Alta de Nuevo Activo** y del botón de lápiz **Editar activo** en cada fila del inventario. La ficha técnica también ofrece **Editar activo**. El formulario compartido carga los datos actuales desde el servidor y permite cambiar categoría, ubicación, nombre/modelo, serie, responsable y frecuencia de mantenimiento. Los errores se muestran dentro del formulario; un conflicto con otra sesión ofrece **Cargar datos actuales**. Una respuesta de guardado correcta actualiza el listado y sincroniza los selectores, el tablero y la auditoría.

La lógica está en `public/js/features/asset-editor.js`; el formulario está en `resources/views/app/modals/asset-create.blade.php`. Consultar [uso, contrato de API y evidencia](CRUD_ACTIVOS.md).

## Historial de técnicos en Estadísticas y costos

La sección **Reportes por técnico** muestra a todos los especialistas, excluye al jefe técnico y conserva visibles las cuentas inactivas con su historial. Cada fila se despliega para consultar reportes, dispositivos, fechas, estados, períodos de asignación, motivos de reasignación y el detalle del reporte. Los técnicos sin asignaciones aparecen con sus contadores en cero.

**Asignados en total** cuenta reportes únicos que alguna vez estuvieron asignados al técnico. **Actuales** cuenta los que siguen bajo su responsabilidad y aún no están finalizados. **Solucionados** cuenta los finalizados cuyo responsable al cierre fue ese técnico; participar antes de una reasignación no acredita la solución. Si un historial antiguo no permite identificar al responsable final, no se atribuye la resolución.

El catálogo procede de `technicians_ranking` en `/api/estadisticas`; el historial utiliza los reportes y asignaciones ya autorizados de `/api/reportes`. El cálculo se actualiza al renderizar la sección, incluida la navegación posterior a una asignación o cierre. La presentación está en `public/js/features/technician-history.js`, el cálculo en `public/js/lib/technician-history.js` y los estilos en `public/css/technician-history.css`.

## Pruebas

El selector compartido de dispositivos está en `public/js/ui/asset-select.js`. Se usa en reportes, mantenimiento, tareas del calendario e historial. Conserva el identificador en el `select` original y valida las búsquedas pendientes también en campos opcionales, para evitar guardar una tarea general por error.

Para comprobar sus interacciones con el DOM real y los formularios:

```bash
node scripts/frontend/serve-browser-checks.mjs
```

Abrir `http://127.0.0.1:8765/tests/Frontend/asset-select.browser.html`. La página ejecuta 16 pruebas con datos ficticios y respuestas API simuladas; no escribe en la base. Incluye agrupación, búsqueda, teclado, validación, reinicio, sincronización, reportes, mantenimiento, calendario e historial. Detener el servidor con Ctrl+C. El servidor solo publica la página de pruebas y los recursos JS/CSS necesarios.

El mismo servidor ofrece `http://127.0.0.1:8765/tests/Frontend/maintenance-history.browser.html`, con seis comprobaciones del buscador de revisiones e inspecciones. El filtrado local está en `public/js/lib/maintenance-history.js`: admite palabras sin tildes, dispositivo, categoría, ubicación, técnico cuando consta, detalles, observaciones y fechas ISO o DD/MM/AAAA. Incluye las observaciones donde se guarda quién registró una intervención. La búsqueda conserva los registros históricos, el orden y el foco del campo; muestra el contador y permite limpiar el filtro.

```bash
php artisan test
node --test tests/Frontend/*.test.mjs
```

Las pruebas PHP utilizan SQLite en memoria. Los archivos HTML de la raíz y de `public/` solo conservan una redirección; el acceso por doble clic o un servidor estático no proporciona la sesión ni la API.

Ver también [estructura del código](../ESTRUCTURA.md), [mapa 3D](../arquitectura/FLOORPLAN_3D.md) y [guía histórica del prototipo](../historico/INSTRUCCIONES_FRONTEND_PROTOTIPO.md).
