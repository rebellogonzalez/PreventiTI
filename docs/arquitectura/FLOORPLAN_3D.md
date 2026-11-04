# Floorplan operativo 3D

La ruta `/#mapa` integra WebGL / Three.js **0.180.0** con la SPA JavaScript existente. No requiere Node, un proceso de build ni un CDN para el motor 3D. La copia local y la licencia MIT están en `public/js/vendor/three/`. OrbitControls solo modifica su import para resolver el módulo local.

## Arquitectura

| Responsabilidad | Archivo |
| --- | --- |
| Entrada SPA, carga diferida y limpieza al cambiar de ruta | `public/js/app.js` |
| Búsqueda, filtros, panel de detalle, sincronización y edición | `public/js/floorplan/index.js` |
| Cámara, iluminación, geometría, raycasting y controles | `public/js/floorplan/scene.js` |
| Adaptación de datos y distribución automática sin persistencia | `public/js/floorplan/model.js` |
| Interfaz responsive y etiquetas | `public/css/map-styles.css` |
| Geometría de salas y correspondencia con ubicaciones | `config/floorplan.php` |
| Ensamblado de inventario, estado, incidencias y coordenadas | `app/Services/FloorplanService.php` |
| API y autorización | `app/Http/Controllers/Api/AssetController.php`, `routes/api.php` |
| Persistencia independiente de posiciones | `database/migrations/2026_10_04_000001_create_asset_map_positions.php` |

Los recursos se editan directamente en `public/js/` y `public/css/`; las copias idénticas de la raíz se retiraron el 21/10/2026. El mapa requiere el backend autenticado: no usa inventario de demostración como reemplazo cuando falla la API. Los estilos anteriores del mapa se sustituyeron; los demás módulos conservan su arquitectura.

## Datos y distribución

- `activos`, categorías y responsables siguen siendo la fuente de verdad del inventario.
- `activo_ubicaciones` determina la sala. La correspondencia usa el par exacto piso/sala, sin inferir ubicación a partir del número del activo.
- `config/floorplan.php` describe dimensiones y funciones de los espacios. Son medidas de diseño esquemáticas, **no un relevamiento físico**.
- Los 30 terminales ocupan dos sectores operativos contiguos y abiertos (17 + 13 según la base actual). Los escritorios se generan únicamente para terminales y notebooks registrados.
- Hay seis notebooks en la sala técnica y cuatro en gerencia/coordinación. Uno de estos últimos pertenece al líder: se conserva esa ubicación registrada, sin trasladarlo silenciosamente para hacer coincidir el dibujo con una propuesta organizativa. Los otros tres corresponden a gerencia/administración. Dos notebooks de especialistas carecen de responsable en el inventario y se muestran como tales.
- La recepción solo incorpora asientos/mesa decorativos y sus dos pantallas reales. IT, apoyo administrativo y accesos representan sus dispositivos reales; no se agregan servidores u otros equipos por el nombre de una sala.
- Una ubicación nueva o ausente crea un espacio visual identificable como pendiente de configurar. Ningún activo desaparece por no coincidir con el plano inicial.

### Coordenadas

`asset_map_positions` tiene una fila opcional por activo: `activo_id`, `floor`, `room`, `x`, `z`, `elevation`, `rotation`, `updated_by`, timestamps. X/Z son proporciones dentro de la sala; elevación está en metros y giro en grados. Los formularios muestran X/Z como porcentajes.

Sin una fila válida, el adaptador distribuye los equipos por categoría y orden estable de ID, y marca `source: schematic`. No escribe esa propuesta en la base. Una posición guardada se marca `confirmed`; tiene prioridad sobre la distribución automática. Si cambia la sala del inventario, la posición anterior se considera obsoleta y requiere confirmación nueva.

El administrador puede ajustar coordenadas en el panel del activo. La API valida límites, permisos y que la sala siga siendo la misma al guardar. No cambia ni crea activos, usuarios o ubicaciones. El guardado deja una entrada en la auditoría existente.

## API y monitoreo

- `GET /api/floorplan`: exige el permiso existente `AssetPolicy::viewAny`. Devuelve salas, activos, estados, permisos de detalle, reportes autorizados y coordenadas confirmadas.
- `PATCH /api/floorplan/assets/{id}/position`: exige `AssetPolicy::update` (administrador). Cuerpo: `x`, `z`, `elevation`, `rotation`, `floor`, `room`. Respuestas 422 para coordenadas inválidas y 409 si cambió o falta la ubicación.
- La ficha técnica continúa utilizando `GET /api/activos/{id}` y el modal existente. Se corrigieron los alias de datos que faltaban y el historial que imprimía interpolaciones literales.
- Los estados se derivan de `estado_activo`, `AlertService` y reportes relacionados. Prioridad: fuera de servicio/retirado, desconectado, incidencia activa, advertencia preventiva, operativo, desconocido. Un reporte `Finalizado` ya no cuenta como activo. Advertencia preventiva no se presenta como una incidencia inventada.
- Se muestran conteos operativos agregados; las descripciones de reportes se filtran con `ReportPolicy`. El acceso a la ficha respeta `AssetPolicy::view`.
- Actualización cada 30 segundos mientras la página es visible y actualización manual. Si falla, se identifica la lectura como desactualizada y se conserva la última respuesta correcta. No se simulan sondas ni conectividad: “Desconectado” requiere que la fuente registre ese estado.

## Navegación y rendimiento

Selección de sala → transición de cámara → equipos identificados → panel contextual. Clic sobre geometría mediante raycasting o botones accesibles equivalentes. Arrastrar desplaza, rueda acerca/aleja y botón derecho gira. En móvil se puede tocar para seleccionar y deslizar para recorrer la página. El botón **Mover mapa** activa un dedo para desplazar y dos dedos para acercar/girar; **Terminar movimiento** devuelve el desplazamiento de la página. Los gestos cancelados, de dos dedos o que vuelven al punto inicial no seleccionan equipos. Hay botones de vista superior, isométrica y restablecimiento; teclado: flechas, `+`, `-`, `Home`, `Escape` sobre el lienzo.

La vista general usa marcadores numéricos pequeños cerca del borde de cada sala, con la misma numeración del explorador. Al entrar a una sala se ocultan los marcadores; los dispositivos conservan su geometría y sus indicadores de estado, sin carteles superpuestos. El nombre completo y el estado del elemento señalado, enfocado con teclado o seleccionado aparecen en una franja fuera del dibujo. La selección mantiene la ficha lateral y el anillo sobre el equipo. Los marcadores incluyen el nombre accesible y devuelven el foco al lienzo al abrir la sala.

En pantallas de hasta 1023 px, incluida la orientación horizontal de iPhone, la descripción y los controles de cámara aparecen debajo del plano. El detalle usa el desplazamiento normal de la página; los botones permiten pasar de la lista al plano. Los campos tienen texto de 16 px y los controles del mapa un área táctil mínima de 44 px. El círculo visible de cada sala es menor que su área táctil. La cámara encuadra las esquinas proyectadas y conserva la vista superior al cambiar el tamaño de pantalla.

La sincronización conserva el editor de coordenadas activo o con cambios pendientes y evita reconstruir selectores sin cambios. Un equipo trasladado sigue seleccionado en su sala actual; las categorías eliminadas no dejan filtros ocultos. El guardado espera cualquier lectura anterior y consulta de nuevo las coordenadas confirmadas. Sin piso y sala registrados se explica cómo habilitar la edición. Una pérdida de WebGL muestra un reintento y mantiene el explorador disponible; la actualización puede reconstruir la escena. Las incidencias del detalle solo incluyen reportes activos autorizados.

El motor carga solo al abrir el mapa. Renderiza cuando cambian la cámara o los datos, limita la densidad de píxeles, reutiliza materiales y omite reconstrucciones cuando la lectura no cambió. Al salir se liberan controles, eventos, observadores, solicitudes, temporizador y recursos WebGL. Se respeta movimiento reducido. Sin WebGL permanece disponible el explorador textual de salas/activos.

## Instalación y validación

```sh
php artisan migrate
php artisan test
node --test tests/Frontend/floorplan.test.mjs
```

La migración añade únicamente la tabla de posiciones. En este entorno se aplicó después de crear `database/backups/pre-floorplan-20261004.sqlite`. La comparación posterior confirmó cero cambios en activos y ubicaciones, 69 dispositivos, 40 usuarios y ninguna coordenada ficticia confirmada.

Pruebas: inventario completo, ubicaciones nuevas/ausentes, autenticación y roles, validación/persistencia de coordenadas, rechazo de ubicaciones obsoletas, estados de reportes, privacidad de descripciones, distribución determinista, búsqueda/filtros y escape de texto. Verificación de navegador: piso/sala/activo, selección directa 3D, ficha existente, filtros y diseño responsive.

Para comprobar el mapa con WebGL real y un inventario ficticio, ejecutar `node scripts/frontend/serve-browser-checks.mjs` y abrir `http://127.0.0.1:8765/tests/Frontend/floorplan.browser.html`. Las 20 pruebas verifican marcadores, foco, selección mediante raycasting, gestos táctiles simulados, filtros, sincronización, pérdida de conexión/contexto, edición, nombres largos o con caracteres HTML y errores JavaScript sin controlar. La página reproduce el contenedor desplazable de la aplicación. No utiliza la sesión ni la base de datos real. Ver [revisión de iPhone](../guias/REVISION_MAPA_IPHONE.md) para el alcance y sus límites.

Para un levantamiento real, ajustar dimensiones en la configuración y confirmar posiciones mediante datos/formulario. La organización física definitiva del líder requiere que su ubicación real esté reflejada primero en el inventario.
