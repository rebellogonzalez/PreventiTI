> Documento histórico del prototipo. Para ejecutar y modificar la aplicación actual, consultar [la guía frontend](../guias/FRONTEND.md).

# PreventiTI — Prototipo Frontend Hipotético e Interactivo (v2.0 Optimizado)

Bienvenido a la versión optimizada del frontend de **PreventiTI: Gestión Preventiva de Activos Tecnológicos**.
Este sistema es una **Single Page Application (SPA) 100% frontend**, interactiva, moderna y adaptativa, desarrollada para simular fielmente todas las funciones operativas, analíticas y de prevención definidas en el proyecto.

---

## 🚀 Cómo Ejecutar y Visualizar el Frontend

No requiere backend, microservicios ni base de datos en servidor.

### Opción 1: Abrir Directamente en el Navegador (Recomendada)
Haga doble clic en el archivo `index.html` o ábralo con cualquier navegador (Chrome, Safari, Edge, Firefox).
En macOS desde la terminal:
```bash
open index.html
```

### Opción 2: Mediante Servidor Estático Local (Opcional)
```bash
python3 -m http.server 8080
```
Y luego abra en su navegador: `http://localhost:8080`

---

## 📱 Mejoras de Usabilidad y Nuevas Áreas Incorporadas

### 1. Panel Lateral Responsivo (Sidebar)
* **En PC de escritorio (`>= 1024px`):** El panel lateral permanece **fijo y visible de forma permanente** a la izquierda, permitiendo una navegación cómoda e ininterrumpida.
* **En Celulares y Tablets (`< 1024px`):** El panel lateral es **desplegable tipo cajón (*off-canvas*)**. Se abre desde el botón hamburguesa del Topbar, incluye un botón de cierre `X`, se cierra tocando fuera (en el fondo oscuro) y se auto-colapsa al seleccionar cualquier sección del menú.

### 2. Tablero Principal Operativo Limpio (`#dashboard`)
* Se eliminaron los gráficos pesados del panel principal para transformarlo en un **centro de mando ágil y ejecutivo**:
  * **4 KPIs Directos:** Parque Total (78), Activos en Atención, Activos Críticos y Reportes Registrados.
  * **Notificaciones Persistentes:** El tablero muestra a administradores y líderes técnicos las notificaciones creadas por cada nuevo reporte, con estado leído/no leído.
  * **Semáforo Preventivo Interactivo:** Filtros de 1 clic para ver activos Normales, en Atención o Críticos.
  * **Tareas Programadas para Hoy (2 de Septiembre):** Lista de actividades del día con horarios, responsables y activos intervenidos.
  * **Insumos Críticos en Alerta:** Equipos con insumos $\le 20\%$ con botón de reposición directa.
  * **Próximos Vencimientos de Mantenimiento y Bitácora de Auditoría en Tiempo Real (RF-12).**

### 3. Nueva Área de Calendario y Agenda de Tareas (`#calendario`)
* **Contadores Métricos Superiores:**
  * **Tareas para el Día:** Cantidad de tareas programadas para la fecha seleccionada.
  * **Tareas Esta Semana:** Conteo de tareas en la semana en curso (Semana 1 / Mes 1).
  * **Tareas Este Mes:** Cantidad total de tareas planificadas en el mes (19 actividades).
* **Cuadrícula de Calendario Mensual Interactiva:**
  * Muestra el mes de Septiembre 2026 día por día con pastillas de colores según el tipo de tarea (*Mantenimiento* verde, *Reporte* rojo, *Consumible* ámbar, *Red* violeta).
  * Al hacer clic en cualquier día, se actualizan el contador diario y la lista detallada de tareas preordenadas de esa fecha.
* **Detalle de Tareas del Día y Filtro por Tipo:** Muestra horario, título, activo asociado, responsable y prioridad, con botón interactivo para marcar la tarea como *Completada* o *Pendiente*.
* **Modal para "Agendar Tarea":** Permite programar un nuevo servicio o mantenimiento para cualquier fecha del calendario.

### 4. Nueva Área de Estadísticas, Inteligencia y Costos (`#estadisticas`)
* **Módulo Financiero y Presupuestos:**
  * Presupuesto Anual asignado ($4.800.000 ARS), Ejecutado ($2.640.000 ARS, 55%) y Remanente disponible ($2.160.000 ARS).
  * **ROI Preventivo:** Cálculo del ahorro económico generado por paradas de línea evitadas ($14.250.000 ARS).
  * Desglose presupuestario en repuestos de hardware, consumibles y mano de obra técnica.
* **Gráficos Interactivos (Chart.js):**
  * Gráfico de Dona: Distribución de los 78 activos en las 10 familias tecnológicas.
  * Gráfico de Barras: Comparativa de mantenimientos ejecutados vs proyectados por hito trimestral.
* **Componentes y Repuestos Más Reemplazados:** Tabla con conteo de unidades cambiadas, costos unitarios en ARS, total invertido y motivo técnico de la sustitución (fuentes ATX, cables USB blindados, rodillos térmicos, baterías VRLA de UPS, filtros de climatización).
* **Reportes por Usuario:** Ranking de empleados y sectores que han registrado situaciones.
* **Incidencias por Dispositivo:** Lista de los equipos con mayor tasa de falla histórica para enfocar acciones preventivas.
* **Incidencias Recurrentes:** Matriz de patrones detectados, causas raíz identificadas y contramedidas preventivas aplicadas.

---

## 👥 Simulador de Roles RBAC
En la barra superior (Topbar) puede alternar en cualquier momento entre perfiles simulados:
* **Roberto Gómez (Administrador):** Gestión patrimonial, altas de activos y bajas lógicas.
* **Ignacio "Nacho" de la Rosa (Técnico Líder):** Consulta de notificaciones de reportes y control perimetral.
* **Daniel Sanz (Técnico Especialista):** Ejecución técnica de servicios, calibración y resolución de incidencias.
* **Personal Operativo (Cajeros y Salón):** Reporte rápido de anomalías en sus puestos de trabajo.

---

## 🔒 Cumplimiento de Restricciones
* **Integración Laravel:** Los reportes y sus notificaciones se persisten en el servidor; `localStorage` conserva sólo el estado de apoyo para la demostración.
