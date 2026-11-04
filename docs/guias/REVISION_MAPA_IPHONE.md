# Revisión del mapa con prioridad en iPhone

Fecha: 3 de noviembre de 2026.

Se revisaron la escena 3D, etiquetas, cámara, controles, filtros, navegación, detalle, edición de coordenadas, sincronización, recuperación de errores, API del mapa y su integración con el contenedor desplazable de la aplicación.

## Problemas corregidos

| Problema observado | Corrección |
| --- | --- |
| Un arrastre que terminaba donde empezó abría un dispositivo. | Se registra el movimiento durante todo el gesto; cancelar o usar dos dedos tampoco selecciona. |
| Deslizar un dedo cambiaba la descripción durante el gesto. | La vista previa ignora movimiento táctil y arrastres. |
| El plano capturaba el desplazamiento de la página. | En móvil se toca para seleccionar y se activa **Mover mapa** para manipular la cámara. |
| Controles pequeños, filtros difíciles de leer y ayuda para ratón. | Áreas táctiles de 44 px, campos de 16 px, etiquetas visibles y ayuda según el modo de uso. |
| El detalle quedaba dentro de otro panel desplazable. | En móvil se lee en el desplazamiento normal de la página, con accesos entre plano y lista/detalle. |
| Los iPhone grandes en horizontal usaban la distribución de escritorio. | La disposición móvil abarca hasta 1023 px. |
| Algunos marcadores quedaban recortados y la orientación restablecía la vista isométrica. | Encuadre calculado con las esquinas proyectadas; se conserva la vista superior al redimensionar. |
| Cada actualización reconstruía las opciones de los selectores. | Solo se reemplazan cuando cambia el catálogo. |
| Las lecturas automáticas borraban coordenadas pendientes al cerrar el teclado. | Se conserva el formulario activo o modificado. |
| Una lectura iniciada antes de guardar podía impedir mostrar el resultado confirmado. | El guardado espera esa lectura y solicita otra después de confirmar. |
| Un equipo trasladado perdía la selección; categorías eliminadas dejaban filtros invisibles. | Se actualizan la sala seleccionada y los filtros obsoletos. |
| Actualizar después de perder WebGL ocultaba el aviso sin recuperar el plano. | La escena se reconstruye; también hay un reintento directo y recuperación del contexto. |
| El detalle de incidencias activas incluía reportes cerrados. | La API entrega únicamente los reportes activos que el usuario puede consultar. |
| Se ofrecía guardar coordenadas sin piso/sala, aunque la API siempre lo rechazaba. | Se mantiene el equipo visible y se explica que primero debe registrarse su ubicación. |

## Validación

- 29 pruebas JavaScript existentes aprobadas.
- 7 pruebas de API del mapa aprobadas, con 31 comprobaciones y SQLite en memoria.
- 20 pruebas de navegador con WebGL real y datos ficticios; incluyen reproducción de los ocho fallos funcionales iniciales, selección, filtros, gestos, conexión, edición, recuperación y ausencia de errores JavaScript sin controlar.
- Tamaños revisados: 320×568, 375×667, 390×844, 430×932, 844×390 y 932×430; también la vista de escritorio. Sin desbordamiento horizontal de la sección.
- Revisión visual del plano general, sala, dispositivo y accesos al panel en el contenedor desplazable.

Los tamaños de iPhone se simulan en el navegador de escritorio. Los eventos táctiles y la pérdida del contexto se provocan desde las pruebas. Esta revisión no sustituye una comprobación en Safari de un iPhone físico con teclado, gestos multitáctiles y suspensión real de la aplicación.

## Repetir las pruebas

```sh
node --test tests/Frontend/*.test.mjs
php artisan test --filter=FloorplanTest
node scripts/frontend/serve-browser-checks.mjs
```

Abrir `http://127.0.0.1:8765/tests/Frontend/floorplan.browser.html`. Las pruebas de navegador usan una API simulada y las de PHP una base en memoria; no modifican el inventario real.
