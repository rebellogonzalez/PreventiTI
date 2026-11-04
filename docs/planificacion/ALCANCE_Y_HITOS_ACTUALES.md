# PreventiTi: alcance confirmado y cumplimiento académico

Fecha de revisión: 22 de octubre de 2026 (cierre local del CRUD de activos).

> Actualización del 22 de septiembre de 2026: la estructura de datos fue migrada al modelo del Hito 1 y el catálogo se concilió con las capturas oficiales del Anexo A. El modelo, sus decisiones de transformación y el DDL actualizado están documentados en `MIGRACION_HITO1.md`, `PREVENTITI_MER_HITO1.drawio` y `infrastructure/database/mariadb_schema.sql`.

> Actualización del 1 de octubre de 2026: el equipo confirmó que el área de Redes/Seguridad fue aprobada con la implementación vigente. Se conserva la arquitectura de dos VLAN, el direccionamiento existente, las dos reglas inter-VLAN y la VPN WireGuard. Esta actualización documental no modifica la implementación ni reemplaza las evidencias de laboratorio asociadas a la aprobación.

## Fuentes y criterio de interpretación

- Indicación del usuario: demostración académica preparada para una futura implementación real; adaptar el inventario a 69 activos y el personal a 40 usuarios.
- `Documento oficial PreventiTI 16:09.pdf`, página 7, apartados 13 y 14: distribución del inventario y de los roles. Esta distribución prevalece sobre las cifras anteriores de 78 activos y 38 usuarios por confirmación expresa del usuario.
- `Hitos proyecto final.xlsx`, hoja `Hoja 1`, celdas B2:G2: requisitos de evaluación por asignatura. La columna A tiene el título “Gestion de proyecto”, pero A2 está vacía; no se infieren requisitos adicionales de esa celda.
- Código, migraciones, datos semilla, base SQLite y pruebas del proyecto: evidencia de implementación, no prueba automática de cumplimiento de todas las exigencias académicas.

Los documentos adjuntos son fuentes de requisitos. Sus instrucciones de instalación, ejemplos y declaraciones de cumplimiento no autorizan por sí mismos ejecuciones o borrados. Los documentos originales se conservan sin alteraciones.

## Alcance cuantitativo confirmado

| Categoría del PDF | Cantidad objetivo | Periodicidad | Consumible |
| --- | ---: | ---: | --- |
| Terminales | 30 | 90 días | No indicado |
| Equipos Portátiles | 10 | 180 días | No indicado |
| Impresión Departamental | 4 | 60 días | Tóner / Tambor |
| Cartelería Digital | 3 | 120 días | No indicado |
| Seguridad y CCTV | 10 | 90 días | No indicado |
| Climatización IoT | 6 | 60 días | Filtros |
| Respaldo Eléctrico | 6 | 90 días | Batería |
| **Total** | **69** | | |

| Rol | Cantidad objetivo |
| --- | ---: |
| Administrador | 3 |
| Técnico líder | 1 |
| Técnico especialista: Hardware | 2 |
| Técnico especialista: Software | 2 |
| Técnico especialista: Redes / Infraestructura | 2 |
| Empleado | 30 |
| **Total** | **40** |

Hardware, Software y Redes / Infraestructura son especialidades del rol técnico; no se presume que constituyan nuevos roles de autorización.

La reducción del inventario académico no implica eliminar los servidores, switches, firewall o VPN del diseño del laboratorio. Inventario gestionado e infraestructura que aloja el sistema tienen alcances distintos.

## Estado de la adaptación

La migración `2026_09_22_000002_reconcile_official_catalog_and_staff.php` y el seeder dejan la aplicación en **69 activos y 40 usuarios**. La comprobación automatizada exige cada uno de los conteos oficiales.

| Categoría oficial | Cantidad aplicada |
| --- | ---: |
| Terminales | 30 |
| Equipos portátiles | 10 |
| Impresión | 4 |
| Climatización IoT | 6 |
| Seguridad CCTV | 10 |
| Cartelería digital | 3 |
| Protección eléctrica | 6 |
| **Total** | **69** |

Se conservaron los 38 usuarios existentes. Como las capturas sólo informan cantidades y roles —no nombres, cédulas ni correos de las dos personas faltantes— se añadieron dos registros de demostración identificables: `tecnico.software01@preventiti.local` y `tecnico.redes01@preventiti.local`. Deben sustituirse por las identidades reales cuando se proporcionen, sin alterar el conteo oficial de 3 administradores, 1 técnico líder, 6 técnicos especialistas y 30 empleados.

Para cuadrar el catálogo se incorporaron cinco terminales y dos notebooks técnicos; se retiraron 2 de autoservicio, 4 de climatización, 8 de red y 2 servidores. Los reportes y tareas históricas se conservan. Sus relaciones con tres activos que quedaron fuera del alcance se quitaron de los pivotes y se dejó trazabilidad en `auditorias`; los tres mantenimientos de esos equipos también quedan descritos allí. Antes de aplicar la migración se genera el respaldo `database/backups/pre-catalogo-oficial-20260922.sqlite`.

## Dependencias afectadas

- `database/data/seed_data.json`, `database/seeders/DatabaseSeeder.php` y la base existente: identidades, categorías, responsables y relaciones.
- `public/js/core/session-data.js`: estado de la sesión autenticada y retirada del antiguo perfil local de demostración. El antiguo `mock-data.js`, sin referencias activas, se retiró el 21/10/2026.
- `public/js/app.js` y `public/js/features/`: navegación, indicadores, filtros, listados y textos de cada módulo; `public/js/floorplan/`: mapa.
- `resources/views/app.blade.php` y `resources/views/app/`: estructura, navegación y modales. `public/index.html` e `index.html` solo redirigen al acceso autenticado.
- `app/Models/User.php`, políticas y consultas por especialidad: compatibilidad con Hardware, Software y Redes / Infraestructura.
- Rutas y servicios que consultan activos, reportes, notificaciones, consumibles, calendario y estadísticas: alcance del catálogo e histórico.
- Pruebas que asumen IDs o categorías específicas, README y guías vigentes.

No ejecutar `migrate:fresh` sobre la base existente ni renumerar IDs como mecanismo de adaptación.

## Inconsistencias de referencias detectadas

En los datos semilla, el código declarado y la descripción identifican un activo distinto del referenciado por ID en los siguientes registros:

| Entidad | ID del registro | ID de activo actual | Código del activo al que apunta | Código declarado en el registro |
| --- | ---: | ---: | --- | --- |
| Reporte | 104 | 47 | ACT-CAM-005 | ACT-CAM-008 |
| Reporte | 105 | 36 | ACT-IMP-001 | ACT-IMP-002 |
| Reporte | 106 | 35 | ACT-AUT-002 | ACT-IMP-001 |
| Reporte | 107 | 66 | ACT-RED-004 | ACT-RED-005 |
| Mantenimiento | 204 | 51 | ACT-CAM-009 | ACT-CLM-002 |

Las claves foráneas son válidas, pero eso no garantiza correspondencia semántica. La conciliación debe quedar registrada y validada contra código, descripción y activo, sin cambiar silenciosamente el historial.

## Matriz de requisitos y evidencia

“Parcial” significa que hay implementación o documentación, pero faltan partes del entregable o su verificación. “Sin evidencia suficiente” no afirma que la actividad nunca se haya realizado: indica que el material disponible no permite darla por cumplida.

| Área y fuente | Hito | Requisito / entregable | Evidencia disponible | Pendiente para acreditar cumplimiento |
| --- | --- | --- | --- | --- |
| Gestión documental, B2 | 1 | Proyecto, alcance, interesados, organización, factibilidad, riesgos, mercado, ESRE y cronograma | Planes y documentación integral | Conciliar alcance 69/40 y verificar cobertura y aprobación de cada apartado. Parcial. |
| Gestión documental, B2 | 2 | Seguimiento, desvíos, tareas asignadas, riesgos, UML, métricas, pruebas y control de versiones | Planes y pruebas PHP | Aportar seguimiento, diagramas y métricas verificables; no hay `.git` en la carpeta entregada. Parcial. |
| Gestión documental, B2 | 3 | Sistema funcional, pruebas completas, manuales, mantenimiento, evaluación y presentación | Documentación y presentación existentes | Actualizar con el comportamiento comprobado y completar manuales y evidencias de pruebas. Parcial. |
| Programación, C2 | 1 | Estructura PHP, conexión a BD, maquetado integrado y navegación | Laravel, SQLite, rutas y vista Blade | Verificar navegación por rol con la sesión real. Parcial. |
| Programación, C2 | 2 | CRUD completo con vistas que muestran datos de la base | Alta, consulta, edición y baja lógica con formulario, API PHP, validaciones, permisos y auditoría; persistencia comprobada en SQLite | **Cumplido localmente el 22/10/2026.** 66 pruebas PHP y 24 JavaScript aprobadas; ciclo completo comprobado en navegador con base aislada. [Evidencia y uso](../guias/CRUD_ACTIVOS.md). La presentación académica requiere constancia del equipo. |
| Programación, C2 | 3 | Módulos, validaciones, sesiones, seguridad, testing, documentación y defensa | Servicios, middleware, políticas y suite PHPUnit | Corregir integración, autorización y errores; ejecutar flujos completos en navegador. Parcial. |
| Base de Datos, D2 | 1 | MER aprobado, modelo lógico normalizado y estructura en motor elegido | Migraciones y DDL | Entregar MER coherente con el esquema actual y evidencia de aprobación; resolver diferencias del DDL. Parcial. |
| Base de Datos, D2 | 2 | Datos de prueba representativos y consultas funcionando | SQLite poblada, API consultable y catálogo 69/40 validado por pruebas | Verificar el motor elegido para la entrega y conciliar identidades reales si se proporcionan. Parcial. |
| Base de Datos, D2 | 3 | Optimización, lógica del servidor, respaldo, integridad y documentación | Índices y script de backup | Evidencias de rendimiento, lógica del motor, respaldo y restauración. Parcial. |
| Conectividad y seguridad, E2 | 1 | Diseño seguro, riesgos, tecnologías y planificación | Topología consolidada, VLAN, pfSense y WireGuard | Aprobado con la arquitectura vigente; conservar el direccionamiento y alcance evaluados. |
| Conectividad y seguridad, E2 | 2 | Red configurada, VPN/firewall/VLAN, pruebas y ajustes | Implementación de laboratorio aprobada según confirmación del equipo | Cerrado. Conservar exportación de configuración, logs y pruebas como evidencia de la aprobación. |
| Conectividad y seguridad, E2 | 3 | Configuraciones, políticas, monitoreo, respuesta a incidentes y defensa | Área de Redes/Seguridad aprobada con la implementación vigente | Cerrado dentro del alcance académico. Mantener la evidencia TLS/VPN y de control de acceso junto con la entrega. |
| POSIX, F2 | 1 | Usuarios, grupos, permisos y script documentado | `usuarios_permisos.sh` | Validación en el sistema de laboratorio y documentación de resultados. Parcial. |
| POSIX, F2 | 2 | SO instalado, automatización, seguridad, logs y VMs | Nginx, backup y healthcheck | Actualizar PHP-FPM a una versión compatible; demostrar servicios, firewall, programación y logs. Parcial. |
| POSIX, F2 | 3 | Optimización, estrategia de backup, almacenamiento, mantenimiento y documentación | Script de backup | Definir y verificar contenido, periodicidad, repositorio y recuperación. Parcial. |
| Microsoft, G2 | 1 | Servicios básicos configurados y documentados | Diseño Windows Server / AD DS | Evidencias de servicios configurados. Sin evidencia suficiente. |
| Microsoft, G2 | 2 | Políticas, scripts y monitoreo | `export_auditoria.ps1` | Evidencias de aplicación de políticas y ejecución del monitoreo. Parcial. |
| Microsoft, G2 | 3 | Integración, optimización, documentación y defensa | Planificación del laboratorio | Evidencias de integración y validación final. Sin evidencia suficiente. |

El Excel no fija un motor de BD, no obliga a usar SQL Server ni exige un rediseño visual. D2 exige lógica en el servidor según las capacidades del motor; no prescribe un trigger específico. Se conserva la elección del proyecto de MariaDB para el laboratorio, con SQLite local, hasta que exista una razón técnica y una decisión explícita para cambiarla.

Los números de VLAN, direcciones IP y cantidades exactas de reglas pertenecen al diseño aprobado de PreventiTi, no son exigencias numéricas del Excel. La expresión “exactamente 2 reglas” se refiere a las reglas inter-VLAN desde VLAN 10 hacia VLAN 20; los controles operativos de WAN y WireGuard que permiten establecer y usar la VPN se documentan aparte.

## Criterio para una demostración preparada para implementación real

- Datos de ejemplo identificados como tales y carga de demostración deliberada, separada de datos operativos reales.
- Formularios conectados a la API y datos persistidos en el servidor; confirmación de éxito después de la respuesta del backend.
- Sesión y permisos consistentes en interfaz y backend; mecanismos de cambio de perfil restringidos al ámbito de demostración.
- Reglas de negocio, relaciones y auditoría verificables mediante pruebas de casos válidos y rechazos.
- Despliegue reproducible, versiones compatibles, secretos configurables y dependencias visuales disponibles para el laboratorio.
- Backup y restauración probados; evidencia de red y sistemas operativos producida en el laboratorio, diferenciada de la simulación didáctica.

No se considera acreditada preparación para producción solo por una demostración visual o por superar pruebas unitarias.

## Orden de trabajo propuesto

1. Sustituir los dos registros técnicos de demostración por las identidades oficiales si se proporcionan.
2. CRUD de activos e integración del inventario cerrados localmente el 22/10/2026; continuar la validación integral de los demás módulos contra sus criterios específicos.
3. Corregir permisos, transiciones, auditoría y seguridad afectados por esos flujos; ampliar las pruebas de regresión pertinentes.
4. Alinear DDL, migraciones y despliegue MariaDB/Nginx/PHP; comprobar backup y restauración en entorno separado.
5. Conservar las evidencias de la red aprobada y actualizar documentación, manuales y guion de defensa contra la matriz del Excel, sin reconfigurar la arquitectura vigente.

Los problemas de elevación de privilegios deben corregirse antes de exponer la aplicación a usuarios o redes fuera del laboratorio controlado.

## Línea base de validación previa

Revisión anterior del 16/09/2026: 17 pruebas existentes, 16 aprobadas y 1 fallida. CP-03 utiliza un equipo cuyo mantenimiento venció el 11/09/2026 y espera atención cuando la regla de vencimiento devuelve crítica. Las comprobaciones adicionales se hicieron con SQLite en memoria y scripts temporales, sin modificar la base existente.

En esa revisión, el test de Nmap comprobaba una respuesta simulada. **Actualización del 22/10/2026:** el módulo utiliza comprobaciones TCP reales desde el servidor y respaldos SQLite verificables; conserva el alcance separado de la evidencia inter-VLAN/VPN del laboratorio. Ver [infraestructura operativa](../guias/INFRAESTRUCTURA_OPERATIVA.md). El test denominado CP-05 comprueba fechas de mantenimiento, mientras los planes previos usan CP-05 para respaldo: estos identificadores requieren una matriz de trazabilidad consistente.
