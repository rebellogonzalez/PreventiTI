# Generación auxiliar de documentos

`create_hito1_pdf.py` conserva el generador de la entrega de Conectividad y Seguridad. Antes estaba en `tmp/pdfs/`; ahora obtiene la raíz del proyecto desde su propia ubicación.

Requiere Python, Pillow y ReportLab, las fuentes Arial de macOS y la imagen externa indicada por `DIAGRAM` en el script. Se conserva `.venv` porque puede contener estas dependencias. El script escribe `output/pdf/PreventiTI_Hito_1_Conectividad_y_Seguridad.pdf`; ejecutarlo reemplaza ese PDF, por lo que debe reservarse para una regeneración deliberada de la entrega.

`create_hito2_pdf.py` genera el documento de Gestión Documental del Hito 2 en `output/pdf/PreventiTI_Hito_2_Avance_y_Seguimiento.pdf`. Lee el backlog vigente y construye el índice, las tablas y diagramas UML con ReportLab. Para regenerarlo desde la raíz del proyecto:

```bash
.venv/bin/python scripts/docs/create_hito2_pdf.py
```

Las estimaciones de hardware, costos y servicios del informe están rotuladas como tales; los estados de hitos y horas informados por el equipo se atribuyen a esa fuente. La salida no reemplaza el PDF de Hito 1.

Las previsualizaciones existentes están en `output/previews/pdfs/`. La presentación y su fuente Marp se encuentran en `docs/presentaciones/`.
