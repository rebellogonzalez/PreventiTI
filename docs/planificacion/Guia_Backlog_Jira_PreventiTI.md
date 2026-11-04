# Backlog Jira de PreventiTI

## Alcance

El backlog cubre Gestión de Proyecto, Programación Web, Bases de Datos y Redes/Seguridad. Excluye todas las tareas de Sistemas Operativos POSIX y Microsoft, según el alcance indicado. Para Ignacio solo conserva responsabilidades de Redes/Seguridad y colaboración transversal ajena a esas áreas.

Cada fila es una tarea plana de Jira, con ID de planificación estable `PVT-001` a `PVT-038`, objetivo, criterio de aceptación, responsable previsto y dependencias. Los intervalos Semana 1–12 provienen del cronograma del proyecto; no se inventaron fechas calendario, duraciones ni puntos de historia.

## Fases y estado inicial

El CSV conserva la línea base de planificación preparada antes de la aprobación posterior del área de Redes/Seguridad. Sus estados `pendiente` y `evidencia-parcial` sirven para reconstruir el plan inicial y no sustituyen el estado vigente registrado en `ALCANCE_Y_HITOS_ACTUALES.md`. Al importar o mantener esas tareas en Jira, las tareas de redes deben cerrarse con la evidencia de la implementación aprobada, sin cambiar VLAN, IP ni reglas.

- `fase-previa`: actividades del Hito 1 ya planificadas/realizadas; algunas aún tienen brechas.
- `fase-actual`: actividades del Hito 2 y cierres pendientes que requieren atención.
- `fase-proxima`: actividades de optimización, pruebas finales y defensa del Hito 3.
- `evidencia-presente`: el artefacto o la implementación se ve en los archivos del proyecto; no significa que se haya revalidado ahora ni que el hito esté aprobado.
- `evidencia-parcial`: existe trabajo, pero falta completar o validar parte del entregable.
- `pendiente`: no hay evidencia suficiente de cierre.
- `pendiente-insumo`: depende de recibir datos oficiales que no están en los archivos actuales.

El alcance vigente es 69 activos y 40 usuarios. Hay dos identidades técnicas de demostración sin datos personales oficiales; una tarea separada las deja explícitas. Parte de la documentación anterior aún menciona 78/38 y debe actualizarse.

## Cierre local de inventario — 22/10/2026

Las tareas **PVT-017** (CRUD de activos) y **PVT-018** (inventario conectado a la API) cuentan con implementación y verificación local completas. El formulario permite crear y editar; los cambios se guardan en el servidor, permanecen tras recargar y respetan permisos y validaciones. Se comprobó también la baja lógica y su reversión. La evidencia, los resultados de pruebas y el guion de demostración están en [CRUD de activos](../guias/CRUD_ACTIVOS.md).

Este cierre se registra en la matriz vigente; el CSV conserva la línea base histórica. No implica una actualización de una instancia externa de Jira ni acredita que el parcial se haya presentado.

## Importación

El CSV usa `Work Type=Task`, `Summary`, `Description`, `Assignee` y siete columnas `Labels` repetidas. En el importador de Jira Cloud, mapea cada columna `Labels` al campo Labels para obtener filtros por hito, área, fase, evidencia, responsable y colaboradores. Si la instancia usa el importador clásico, mapea `Work Type` al campo equivalente `Issue Type`.

`Assignee` queda vacío intencionalmente: no se proporcionaron correos ni Atlassian Account IDs. Después de importar, filtra por `responsable-ramiro`, `responsable-daniel` o `responsable-ignacio` y asigna cada lote a los usuarios existentes. No selecciones la opción de crear cuentas automáticamente a partir de los nombres.

El backlog es plano y las dependencias aparecen como IDs PVT en la descripción; no se inventaron claves Jira ni se creó jerarquía Epic/Parent dependiente de la configuración de una instancia. Tras importar, se pueden crear Epics H1/H2/H3 y enlazar los issues filtrando por las etiquetas `hito-1`, `hito-2` y `hito-3`; para bloquear tareas, crea los enlaces Jira usando los IDs PVT indicados.

No se incluyeron fechas de vencimiento, estimaciones, sprints ni prioridades porque las fuentes no fijan esos valores. Se deben definir con el equipo al cargar el proyecto.

## Fuentes y cautelas

La distribución de responsables y ventanas se deriva del cronograma integral; el estado de evidencia se deriva de la matriz revisada el 22/09 y los archivos posteriores de migración. Los informes existentes difieren sobre el resultado de pruebas (16 aprobadas frente a una línea base de 16/17 con una falla); por eso el backlog exige reconciliar y volver a registrar resultados antes de declarar completos los tests. No se ejecutaron pruebas al preparar este backlog.
