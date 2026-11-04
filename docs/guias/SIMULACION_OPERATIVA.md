# Simulación operativa — 22 de octubre de 2026

Los datos nuevos están persistidos en las tablas normales de la aplicación y se pueden consultar, asignar, diagnosticar y completar mediante sus formularios y API. Son datos de prueba, no compras ni incidentes oficiales. Su origen queda identificado en el historial interno y en `simulation_runs`.

## Datos conservados

Se compararon las filas completas antes y después: las 40 cuentas, hashes de contraseñas, perfiles técnicos, jefatura, nombres, direcciones, 69 activos, 7 categorías, ubicaciones y posiciones del mapa permanecen idénticos. También se conservaron los 86 registros de auditoría anteriores, incluidos accesos y emisión de credenciales.

Se reemplazaron exclusivamente 10 reportes de demostración, sus 10 alertas, 2 mantenimientos, 16 consumibles y 19 tareas. El usuario confirmó expresamente que los reportes manuales 108–110 eran pruebas. Los otros registros procedían de la semilla histórica y sus migraciones. No se reiniciaron los identificadores.

## Escenario nuevo

| Datos | Cantidad |
| --- | ---: |
| Reportes finalizados, con diagnóstico e intervención | 24 |
| Reportes en curso | 4 |
| Reportes en diagnóstico, pendientes de resolver | 2 |
| Reportes recibidos, 3 asignados y 3 sin asignar | 6 |
| Reasignaciones con motivo e historial | 2 |
| Mantenimientos preventivos y correctivos | 92 |
| Tareas históricas y próximas | 153 |
| Consumibles, filtros y baterías controlados | 16 |
| Consumibles en umbral de atención | 3 |
| Líneas de costos asociadas a mantenimientos | 25 |

Los seis especialistas tienen cuatro reportes finalizados cada uno y casos pendientes. El jefe técnico dirige las asignaciones y transiciones; no recibe asignaciones como especialista. Los reportes y mantenimientos ocurren después del alta oficial del equipo. Las próximas revisiones se calculan desde el mantenimiento más reciente y la frecuencia configurada de cada activo.

Hay tareas para el día de referencia y los días siguientes. Las tareas completadas corresponden a intervenciones anteriores; las futuras permanecen pendientes. La impresora térmica utiliza etiquetas/ribbon, las láser tóner y los filtros y baterías muestran una estimación de vida útil.

## Presupuesto y costos

Importes estimados en **pesos uruguayos (UYU)**, almacenados en centésimos. Son una hipótesis de mantenimiento de este parque: excluyen sueldos del personal existente, compra de equipos y licencias. No son cotizaciones.

| Concepto | UYU |
| --- | ---: |
| Presupuesto anual | 120.000 |
| Repuestos instalados | 20.740 |
| Insumos utilizados | 3.700 |
| Servicios externos de climatización | 7.200 |
| Total registrado | 31.640 |
| Remanente | 88.360 |

El porcentaje ejecutado es 26,4 %. No se inventa un retorno de inversión ni ahorros por paradas evitadas. El ranking de repuestos suma unidades reales del escenario; el costo unitario mostrado es el promedio ponderado de las líneas registradas. Las estadísticas consultan `maintenance_costs`, `maintenance_budgets`, reportes, mantenimientos y calendario. Ya no dependen de los importes antiguos de `seed_data.json` ni cambian de moneda según el tamaño del importe.

## Casos para comprobar desde la aplicación

Acceder con las cuentas y contraseñas habituales; esta carga no las cambia.

1. Como jefe técnico, abrir `REP-2026-0172`, recibido y asignado: iniciarlo, registrar diagnóstico e intervención y finalizarlo.
2. Abrir `REP-2026-0175`, en diagnóstico: comprobar su informe y finalizarlo cuando se complete la intervención pendiente.
3. Abrir `REP-2026-0171`, en curso: revisar su responsable y continuar el flujo.
4. Consultar `REP-2026-0147`, ya finalizado: comprobar diagnóstico, intervención, fechas e historial de asignación.
5. En Estadísticas y costos, desplegar el historial de cada especialista; en Incidencias y repuestos, comparar unidades y totales.
6. En Calendario, usar Hoy, navegar a los próximos días y completar una tarea. En Insumos, revisar los tres niveles bajos y registrar una reposición.

Esas acciones modifican los datos de prueba de forma normal. Los permisos siguen vigentes: empleados consultan sus propios reportes y los especialistas no reciben facultades del jefe técnico.

## Repetir una carga de forma controlada

El comando está limitado al entorno local SQLite y empieza con una vista previa:

```bash
php artisan simulation:refresh
```

Para aplicar la renovación deliberadamente:

```bash
php artisan simulation:refresh --apply
```

Puede indicarse `--date=YYYY-MM-DD`; en caso contrario se toma hoy en Uruguay. Antes de escribir se crea un respaldo privado mediante SQLite. Los cambios se aplican en una única transacción y se verifica el catálogo protegido antes de confirmar.

Solo se reemplazan filas identificadas por el manifiesto de la simulación. Si se editó una fila o su historial de asignación, la operación se detiene y conserva la base. Los datos ajenos al manifiesto y un presupuesto existente sin origen simulado se conservan. No se debe usar el sembrado completo ni reconstruir la base activa para renovar pruebas.

La adopción inicial utilizó un manifiesto privado de los registros antiguos revisados. Ese manifiesto no debe reutilizarse: sus registros ya fueron reemplazados. `seed_data.json` permanece como fuente histórica del catálogo y del sembrado inicial; no alimenta los costos de la aplicación activa.

## Respaldos y verificación

- Antes de cambios de esquema y datos: `output/backups/simulacion-20261021-175804/database.sqlite` y carpeta `codigo/`.
- Inmediatamente antes del primer reemplazo: `output/backups/simulacion-20261022-122920-74e8cf/database.sqlite`.
- Respaldo de la primera carga y resumen final de la versión corregida: `output/backups/simulacion-20261022-123215-fd547f/`.

Los respaldos contienen información privada y están fuera de `public/`, con permisos restringidos. Para restaurar, trabajar primero sobre una copia y revisar posibles datos añadidos después del respaldo.

Las pruebas cubren preservación de datos, repetición sin duplicados, rechazo al detectar ediciones posteriores, relaciones, fechas de alta, asignaciones, totales, permisos y alertas vinculadas al reporte correcto. Las comprobaciones finales incluyen integridad SQLite, claves foráneas y lectura de reportes, calendario, insumos, mantenimientos, estadísticas, notificaciones y dashboard sobre la base cargada.
