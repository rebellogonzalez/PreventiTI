# Reportes, asignación y alertas

## Alcance

Un reporte registra una situación sobre un único activo exacto. No es un ticket ni una orden de trabajo. Al crearlo, queda dirigido al Jefe Técnico y se genera una alerta persistida vinculada al activo.

No se permiten reportes nuevos sobre activos en estado `Retirado`.

## Flujo de estados

El reporte comienza en `Recibido/Derivado`. El Jefe Técnico asigna un especialista e inicia la atención. A partir de `En curso`, tanto jefatura como el especialista asignado pueden registrar el diagnóstico o finalizar el reporte.

```text
Recibido/Derivado --(técnico asignado)--> En curso
En curso ------------------------------> Diagnóstico --(informe)--> Finalizado
En curso --(informe de accionar)-------------------------------> Finalizado
```

- Jefatura elige el técnico. Al pulsar **Guardar** en «Recibido/Derivado», la asignación y el paso a «En curso» quedan registrados en la misma operación.
- Cada reporte tiene un solo técnico activo a la vez.
- Una reasignación cierra la asignación anterior, guarda fecha y motivo, y abre una nueva.
- Si un reporte recibido ya tenía un técnico asignado, jefatura puede confirmar al mismo especialista o elegir otro. Una reasignación durante el diagnóstico conserva ese estado.
- El paso a `Diagnóstico` exige informe de accionar y diagnóstico final del dispositivo.
- El paso directo de `En curso` a `Finalizado` exige informe de accionar. Al finalizar desde Diagnóstico se puede mantener el accionar registrado o agregar un informe de cierre. Cada intervención nueva conserva etapa, autor, fecha y texto.
- El cierre termina la asignación y conserva al último especialista en el historial. Una gestión abierta desde una versión anterior requiere revisar los cambios antes de guardar.
- Cada envío del formulario tiene un identificador que permite reintentar y recuperar el mismo código de reporte y la misma alerta.

## Persistencia

- `reportes`: estado, prioridad, descripción, cierre, `informe_accionar` y `diagnostico_final`.
- `reporte_activo`: asociación de un reporte con un solo activo mediante una restricción única sobre `reporte_id`.
- `usuario_reporte`: autor del reporte.
- `tecnico_jefe_tecnico`: historial de cada asignación, con técnico, Jefe Técnico, reporte, fecha de inicio, fecha de finalización y motivo de reasignación.
- `alertas` y `activo_alerta`: alerta persistida generada al crear un reporte.

La alerta queda disponible únicamente para el Jefe Técnico. Su estado de lectura es compartido a nivel de alerta; no hay tabla de notificaciones individuales en el modelo vigente.

## Acceso

- Cualquier cuenta activa puede crear un reporte sobre un activo del inventario en una zona autorizada, incluidos los equipos fuera de servicio.
- Administración y el Jefe Técnico pueden consultar todos los reportes.
- Los especialistas consultan sus reportes y aquellos en los que participaron. Los empleados consultan los que crearon.
- Solo jefatura asigna o reasigna técnicos. Al guardar un cambio de estado, el servidor comprueba la cuenta, el estado y la asignación vigente. Un especialista que participó antes de una reasignación puede consultar el historial.

## Activos retirados

La baja lógica cambia el activo a `Retirado` y conserva los reportes y mantenimientos asociados. El administrador y el jefe técnico pueden solicitar la baja desde el inventario o el mapa. La ficha y los reportes autorizados se consultan en **Histórico de dispositivos**.

## Historial de reportes

En **Reportes → Historial** se consultan todos los reportes disponibles para el perfil autenticado, incluidos los finalizados y los de dispositivos retirados. La vista consulta páginas de `/api/reportes/historial` y no hereda los filtros de Kanban o Lista.

- Agrupación por fecha de creación en America/Montevideo, con orden descendente inicial y opción de ver primero los más antiguos.
- Cada entrada muestra estado actual, descripción del reporte generado, informe de intervención, diagnóstico, código e ID del dispositivo, técnico asignado, autor y fecha de cierre. Los datos ausentes se indican explícitamente.
- En reportes finalizados sin asignación vigente se muestra el último técnico registrado. Las asignaciones anteriores y sus motivos se pueden desplegar.
- Búsqueda inmediata por reporte, dispositivo, técnico, descripción, informe y diagnóstico, sin distinguir mayúsculas ni tildes.
- Filtros combinables por estado, dispositivo, técnico y rango inclusivo de fechas; se confirman con **Aplicar filtros**. **Limpiar búsqueda y filtros** restaura el historial completo.
- Paginación de 20 reportes para recorrer todo el historial sin recortar los textos.
