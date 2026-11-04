# Reportes, asignación y alertas

## Alcance

Un reporte registra una situación sobre un único activo exacto. No es un ticket ni una orden de trabajo. Al crearlo, queda dirigido al Jefe Técnico y se genera una alerta persistida vinculada al activo.

No se permiten reportes nuevos sobre activos en estado `Retirado`.

## Flujo de estados

El estado inicial único es `Recibido/Derivado`. Solo el Jefe Técnico puede asignar técnicos y cambiar el estado:

```text
Recibido/Derivado --(técnico asignado)--> En curso
En curso ------------------------------> Diagnóstico --(informe)--> Finalizado
En curso --(informe de accionar)-------------------------------> Finalizado
```

- La asignación es manual; no existe asignación automática.
- Cada reporte tiene un solo técnico activo a la vez.
- Una reasignación cierra la asignación anterior, guarda fecha y motivo, y abre una nueva.
- El paso a `En curso` exige un técnico activo.
- El paso a `Diagnóstico` exige informe de accionar y diagnóstico final del dispositivo.
- El paso directo de `En curso` a `Finalizado` exige informe de accionar.

## Persistencia

- `reportes`: estado, prioridad, descripción, cierre, `informe_accionar` y `diagnostico_final`.
- `reporte_activo`: asociación de un reporte con un solo activo mediante una restricción única sobre `reporte_id`.
- `usuario_reporte`: autor del reporte.
- `tecnico_jefe_tecnico`: historial de cada asignación, con técnico, Jefe Técnico, reporte, fecha de inicio, fecha de finalización y motivo de reasignación.
- `alertas` y `activo_alerta`: alerta persistida generada al crear un reporte.

La alerta queda disponible únicamente para el Jefe Técnico. Su estado de lectura es compartido a nivel de alerta; no hay tabla de notificaciones individuales en el modelo vigente.

## Acceso

- Cualquier cuenta activa puede crear un reporte sobre un activo operativo.
- Administración y el Jefe Técnico pueden consultar todos los reportes.
- Técnicos especialistas y empleados consultan solo los reportes que crearon.
- La asignación y las transiciones de estado están protegidas para el rol `tecnico_lider`.

## Activos retirados

La baja lógica cambia el activo a `Retirado` y conserva los reportes y mantenimientos asociados. La baja física solo puede solicitarla el Jefe Técnico y la API la rechaza si ya existe historial de reporte o mantenimiento, para mantener la trazabilidad.

## Historial de reportes

En **Reportes → Historial** se consultan todos los reportes disponibles para el perfil autenticado, incluidos los finalizados y los de dispositivos retirados. La vista usa los datos de `/api/reportes` y no hereda los filtros de Kanban o Lista.

- Agrupación por fecha de creación, con orden descendente inicial y opción de ver primero los más antiguos.
- Cada entrada muestra estado actual, descripción del reporte generado, informe de intervención, diagnóstico, código e ID del dispositivo, técnico asignado, autor y fecha de cierre. Los datos ausentes se indican explícitamente.
- En reportes finalizados sin asignación vigente se muestra el último técnico registrado. Las asignaciones anteriores y sus motivos se pueden desplegar.
- Búsqueda inmediata por reporte, dispositivo, técnico, descripción, informe y diagnóstico, sin distinguir mayúsculas ni tildes.
- Filtros combinables por estado, dispositivo, técnico y rango inclusivo de fechas; se confirman con **Aplicar filtros**. **Limpiar búsqueda y filtros** restaura el historial completo.
- Paginación de 20 reportes para recorrer todo el historial sin recortar los textos.
