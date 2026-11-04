# Distribución simulada de activos

La distribución se basa únicamente en la imagen de referencia aportada para orientar el mapeo de áreas. No representa un plano real ni se utilizaron los equipos, cantidades o datos visibles en ella como información factual.

| Identificador | Piso/sector | Sala simulada |
|---|---|---|
| 1 | Planta principal | Área administrativa abierta — sector A |
| 2 | Planta principal | Área administrativa abierta — sector B |
| 3 | Planta principal | Atención al cliente |
| 4 | Planta principal | Sala de servidores / IT |
| 5 | Planta principal | Oficina de gerencia y coordinación |
| 6 | Planta principal | Administración y recursos humanos |
| 7 | Planta principal | Sala de personal técnico |
| 8 | Exterior | Perímetro y accesos |

Los 69 activos del catálogo simulado conservan sus categorías, códigos y cantidades vigentes. Solo se remapearon sus ubicaciones a estas áreas. En particular, el notebook del Jefe Técnico se sitúa en Gerencia y los dos notebooks técnicos agregados se sitúan en la sala de personal técnico.

La fuente aplicable al despliegue futuro es la base de datos MariaDB original. El esquema de referencia adaptado se encuentra en `infrastructure/database/mariadb_schema.sql`; la SQLite actual sigue siendo una réplica de desarrollo para macOS.
