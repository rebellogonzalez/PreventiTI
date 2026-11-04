#!/usr/bin/env python3
"""Build the PreventiTI Hito 2 project document as a tagged, navigable PDF."""

from __future__ import annotations

import csv
import os
import re
from pathlib import Path
from xml.sax.saxutils import escape

from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER, TA_LEFT, TA_RIGHT
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.units import mm
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.platypus import (
    BaseDocTemplate,
    Frame,
    PageBreak,
    PageTemplate,
    Paragraph,
    Spacer,
    Table,
    TableStyle,
    NextPageTemplate,
    KeepTogether,
)
from reportlab.platypus.tableofcontents import TableOfContents
from reportlab.graphics.shapes import Drawing, Rect, String, Line, Ellipse, Circle, Polygon


ROOT = Path(__file__).resolve().parents[2]
OUTPUT = ROOT / "output" / "pdf" / "PreventiTI_Hito_2_Avance_y_Seguimiento.pdf"
PAGE_W, PAGE_H = A4
INK = colors.HexColor("#16243A")
NAVY = colors.HexColor("#102840")
BLUE = colors.HexColor("#176B87")
TEAL = colors.HexColor("#2D8B85")
PALE = colors.HexColor("#EAF2F5")
PALE_BLUE = colors.HexColor("#E9F1F8")
PALE_GREEN = colors.HexColor("#E7F3EF")
PALE_AMBER = colors.HexColor("#FFF3D9")
PALE_RED = colors.HexColor("#FCE9E7")
MID = colors.HexColor("#5B6978")
GRID = colors.HexColor("#D4DDE3")
WHITE = colors.white
BODY_FONT = "Helvetica"
BOLD_FONT = "Helvetica-Bold"


def register_fonts() -> None:
    global BODY_FONT, BOLD_FONT
    candidates = [
        ("/System/Library/Fonts/Supplemental/Arial.ttf", "/System/Library/Fonts/Supplemental/Arial Bold.ttf"),
        ("/Library/Fonts/Arial.ttf", "/Library/Fonts/Arial Bold.ttf"),
    ]
    for regular, bold in candidates:
        if os.path.exists(regular) and os.path.exists(bold):
            pdfmetrics.registerFont(TTFont("PreventiArial", regular))
            pdfmetrics.registerFont(TTFont("PreventiArial-Bold", bold))
            BODY_FONT, BOLD_FONT = "PreventiArial", "PreventiArial-Bold"
            return


register_fonts()

styles = getSampleStyleSheet()
styles.add(ParagraphStyle(
    name="CoverKicker", fontName=BOLD_FONT, fontSize=9, leading=13,
    textColor=TEAL, spaceAfter=10, alignment=TA_LEFT, tracking=1.2,
))
styles.add(ParagraphStyle(
    name="CoverTitle", fontName=BOLD_FONT, fontSize=30, leading=35,
    textColor=NAVY, spaceAfter=10,
))
styles.add(ParagraphStyle(
    name="CoverSub", fontName=BODY_FONT, fontSize=14, leading=20,
    textColor=BLUE, spaceAfter=16,
))
styles.add(ParagraphStyle(
    name="CoverMeta", fontName=BODY_FONT, fontSize=10, leading=16,
    textColor=INK,
))
styles.add(ParagraphStyle(
    name="HitoHeading1", fontName=BOLD_FONT, fontSize=17, leading=21,
    textColor=NAVY, spaceBefore=7, spaceAfter=9, keepWithNext=True,
))
styles.add(ParagraphStyle(
    name="HitoHeading2", fontName=BOLD_FONT, fontSize=12.2, leading=16,
    textColor=BLUE, spaceBefore=8, spaceAfter=5, keepWithNext=True,
))
styles.add(ParagraphStyle(
    name="HitoHeading3", fontName=BOLD_FONT, fontSize=9.7, leading=13,
    textColor=INK, spaceBefore=6, spaceAfter=4, keepWithNext=True,
))
styles.add(ParagraphStyle(
    name="HitoBodyText", fontName=BODY_FONT, fontSize=8.9, leading=13.2,
    textColor=INK, spaceAfter=6,
))
styles.add(ParagraphStyle(
    name="Small", fontName=BODY_FONT, fontSize=7.6, leading=10.5,
    textColor=INK, spaceAfter=3,
))
styles.add(ParagraphStyle(
    name="Tiny", fontName=BODY_FONT, fontSize=6.9, leading=8.8,
    textColor=INK,
))
styles.add(ParagraphStyle(
    name="TableHead", fontName=BOLD_FONT, fontSize=7.4, leading=9.3,
    textColor=WHITE,
))
styles.add(ParagraphStyle(
    name="TableCell", fontName=BODY_FONT, fontSize=7.15, leading=9.1,
    textColor=INK,
))
styles.add(ParagraphStyle(
    name="TableCellSmall", fontName=BODY_FONT, fontSize=6.6, leading=8.3,
    textColor=INK,
))
styles.add(ParagraphStyle(
    name="Callout", fontName=BODY_FONT, fontSize=8.6, leading=12.5,
    textColor=INK, spaceAfter=0,
))
styles.add(ParagraphStyle(
    name="TOCTitle", fontName=BOLD_FONT, fontSize=21, leading=26,
    textColor=NAVY, spaceAfter=16,
))
styles.add(ParagraphStyle(
    name="TOC1", fontName=BOLD_FONT, fontSize=9, leading=14,
    leftIndent=0, firstLineIndent=0, textColor=INK,
))
styles.add(ParagraphStyle(
    name="TOC2", fontName=BODY_FONT, fontSize=8, leading=11,
    leftIndent=14, firstLineIndent=0, textColor=MID,
))


def para(text: str, style: str = "HitoBodyText") -> Paragraph:
    return Paragraph(text, styles[style])


def plain(text: str, style: str = "TableCell") -> Paragraph:
    return para(escape(str(text)).replace("\n", "<br/>"), style)


def heading(text: str, level: int = 1) -> Paragraph:
    return para(text, f"HitoHeading{level}")


def callout(title: str, message: str, fill=PALE_BLUE, border=BLUE):
    cell = para(f"<b>{escape(title)}</b><br/>{message}", "Callout")
    t = Table([[cell]], colWidths=[510], hAlign="LEFT")
    t.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, -1), fill),
        ("BOX", (0, 0), (-1, -1), 0.7, border),
        ("LEFTPADDING", (0, 0), (-1, -1), 9),
        ("RIGHTPADDING", (0, 0), (-1, -1), 9),
        ("TOPPADDING", (0, 0), (-1, -1), 7),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 7),
    ]))
    return t


def table(rows, widths, *, header=True, font="TableCell", repeat=1, align="LEFT", padd=4):
    prepared = []
    for ri, row in enumerate(rows):
        cooked = []
        for item in row:
            if isinstance(item, Paragraph):
                cooked.append(item)
            else:
                cooked.append(plain(item, "TableHead" if header and ri == 0 else font))
        prepared.append(cooked)
    t = Table(prepared, colWidths=widths, repeatRows=repeat if header else 0, hAlign=align)
    commands = [
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("GRID", (0, 0), (-1, -1), 0.35, GRID),
        ("LEFTPADDING", (0, 0), (-1, -1), padd),
        ("RIGHTPADDING", (0, 0), (-1, -1), padd),
        ("TOPPADDING", (0, 0), (-1, -1), padd),
        ("BOTTOMPADDING", (0, 0), (-1, -1), padd),
    ]
    if header:
        commands.append(("BACKGROUND", (0, 0), (-1, 0), NAVY))
        commands.append(("ROWBACKGROUNDS", (0, 1), (-1, -1), [WHITE, colors.HexColor("#F6F8FA")]))
    else:
        commands.append(("ROWBACKGROUNDS", (0, 0), (-1, -1), [WHITE, colors.HexColor("#F6F8FA")]))
    t.setStyle(TableStyle(commands))
    return t


def box(d, x, y, w, h, title, body, fill=PALE_BLUE, stroke=BLUE, title_color=NAVY, fs=8):
    d.add(Rect(x, y, w, h, rx=6, ry=6, fillColor=fill, strokeColor=stroke, strokeWidth=1))
    d.add(String(x + 7, y + h - 14, title, fontName=BOLD_FONT, fontSize=fs, fillColor=title_color))
    lines = body.split("\n")
    for i, line in enumerate(lines):
        d.add(String(x + 7, y + h - 27 - i * 10, line, fontName=BODY_FONT, fontSize=fs - 1, fillColor=INK))


def arrow(d, x1, y1, x2, y2, color=BLUE, label=None, labelx=None, labely=None):
    d.add(Line(x1, y1, x2, y2, strokeColor=color, strokeWidth=1.2, endArrow="triangle"))
    if label:
        d.add(String(labelx if labelx is not None else (x1 + x2) / 2,
                     labely if labely is not None else (y1 + y2) / 2 + 4,
                     label, fontName=BODY_FONT, fontSize=6.6, fillColor=MID))


def architecture_diagram():
    d = Drawing(510, 205)
    # User/browser layer
    box(d, 12, 127, 84, 49, "Usuarios", "Admin · jefe\nTécnico · empleado", fill=PALE)
    box(d, 122, 127, 102, 49, "Navegador", "Blade + JS\nUI por rol", fill=PALE_BLUE)
    box(d, 254, 119, 125, 66, "VM 2 · Aplicación", "Ubuntu Server 24.04 LTS\nNginx + PHP-FPM 8.3\nLaravel 13 · API JSON", fill=PALE_GREEN, stroke=TEAL)
    box(d, 408, 127, 91, 49, "VM 3 · Datos", "MariaDB 10.11 LTS\nInnoDB · red privada", fill=PALE_AMBER, stroke=colors.HexColor("#BC8424"))
    arrow(d, 96, 151, 122, 151)
    arrow(d, 224, 151, 254, 151, label="HTTPS", labelx=229, labely=161)
    arrow(d, 379, 151, 408, 151, label="SQL 3306", labelx=382, labely=161)
    # Network / security layer
    box(d, 18, 40, 135, 47, "VM 1 · Borde", "pfSense CE 2.9\nVLAN · firewall · WireGuard", fill=PALE_RED, stroke=colors.HexColor("#B85148"))
    box(d, 205, 40, 125, 47, "Respaldo NAS", "Copia cifrada fuera del host\nretención y restauración", fill=PALE)
    box(d, 378, 40, 119, 47, "VM actual de desarrollo", "SQLite en archivo\nambiente local · no producción", fill=colors.HexColor("#F2F0FA"), stroke=colors.HexColor("#6E62A5"))
    arrow(d, 71, 87, 71, 127, color=colors.HexColor("#B85148"))
    arrow(d, 330, 62, 414, 126, color=TEAL, label="copia gestionada", labelx=343, labely=91)
    d.add(String(10, 16, "Propuesta de despliegue: 3 VM núcleo. SQLite representa el entorno actual; MariaDB y el NAS son destino/infraestructura propuestos.", fontName=BODY_FONT, fontSize=7, fillColor=MID))
    return d


def use_case_diagram():
    d = Drawing(510, 243)
    # Cada actor tiene una franja propia para evitar cruces de asociaciones.
    d.add(Rect(116, 15, 382, 214, fillColor=colors.HexColor("#FBFCFD"), strokeColor=BLUE, strokeWidth=1.2))
    d.add(String(128, 211, "Sistema PreventiTI · todos los perfiles inician sesión", fontName=BOLD_FONT, fontSize=8.5, fillColor=NAVY))
    bands = [
        (184, "Empleado", ["Crear reporte", "Consultar propios"]),
        (136, "Especialista", ["Ver asignados", "Mantenimiento", "Tareas asignadas"]),
        (88, "Jefe técnico", ["Asignar reportes", "Programar tareas", "Revisar métricas"]),
        (40, "Administrador", ["Gestionar activos", "Usuarios y roles", "Auditoría / costos"]),
    ]
    xslots = [157, 278, 399]
    for y, actor, labels in bands:
        x = 31
        d.add(Circle(x + 8, y + 11, 5, fillColor=WHITE, strokeColor=INK, strokeWidth=1))
        d.add(Line(x + 8, y + 6, x + 8, y - 8, strokeColor=INK, strokeWidth=1))
        d.add(Line(x + 1, y, x + 15, y, strokeColor=INK, strokeWidth=1))
        d.add(Line(x + 8, y - 8, x + 2, y - 15, strokeColor=INK, strokeWidth=1))
        d.add(Line(x + 8, y - 8, x + 14, y - 15, strokeColor=INK, strokeWidth=1))
        d.add(String(15, y - 27, actor, fontName=BODY_FONT, fontSize=6.5, fillColor=INK))
        for i, label in enumerate(labels):
            cx = xslots[i]
            w = 88 if len(label) < 16 else 96
            d.add(Ellipse(cx + w / 2, y + 10.5, w / 2, 12.5, fillColor=PALE_BLUE, strokeColor=BLUE, strokeWidth=.8))
            d.add(String(cx + 5, y + 7, label, fontName=BODY_FONT, fontSize=6.3, fillColor=INK))
            d.add(Line(x + 17, y + 1, cx, y + 10, strokeColor=MID, strokeWidth=.6))
    d.add(String(118, 3, "Permisos representativos; consultar la matriz operativa para restricciones y datos visibles.", fontName=BODY_FONT, fontSize=6.5, fillColor=MID))
    return d

def class_diagram():
    d = Drawing(510, 246)
    box(d, 18, 154, 120, 74, "Usuario", "+ id, correo, hash\n+ rol, activo\n+ autenticarse()", fill=PALE_BLUE)
    box(d, 195, 154, 120, 74, "Reporte", "+ id, estado, prioridad\n+ descripcion, fecha\n+ transicionar()", fill=PALE_GREEN, stroke=TEAL)
    box(d, 372, 154, 120, 74, "Activo", "+ id, código, categoría\n+ ubicación, estado\n+ actualizar()", fill=PALE_AMBER, stroke=colors.HexColor("#BC8424"))
    box(d, 62, 38, 120, 74, "Mantenimiento", "+ id, tipo, fecha\n+ resultado, reporte_id\n+ registrar()", fill=PALE)
    box(d, 209, 38, 120, 74, "TareaCalendario", "+ id, título, fecha\n+ técnico_asignado\n+ completar()", fill=PALE)
    box(d, 356, 38, 120, 74, "Consumible", "+ id, nombre, nivel\n+ activo_id\n+ actualizarNivel()", fill=PALE)
    arrow(d, 138, 189, 195, 189, color=BLUE, label="crea / asigna", labelx=145, labely=201)
    arrow(d, 315, 189, 372, 189, color=BLUE, label="asociado a", labelx=326, labely=201)
    arrow(d, 102, 154, 110, 112, color=MID, label="tiene historial", labelx=48, labely=130)
    arrow(d, 253, 154, 269, 112, color=MID, label="programa", labelx=263, labely=132)
    arrow(d, 421, 154, 416, 112, color=MID, label="mantiene", labelx=428, labely=132)
    d.add(String(18, 15, "Modelo conceptual simplificado del dominio actual; no sustituye el MER ni el esquema físico.", fontName=BODY_FONT, fontSize=7, fillColor=MID))
    return d


def sequence_diagram():
    d = Drawing(510, 259)
    actors = [(4, "Empleado / navegador"), (106, "Laravel API"), (208, "Sesión + Policy"), (310, "ReportService"), (412, "Base de datos")]
    centers = []
    for x, name in actors:
        d.add(Rect(x, 222, 94, 26, fillColor=PALE_BLUE, strokeColor=BLUE, strokeWidth=.8))
        d.add(String(x + 4, 232, name, fontName=BODY_FONT, fontSize=6.25, fillColor=INK))
        center = x + 47
        centers.append(center)
        d.add(Line(center, 222, center, 19, strokeColor=GRID, strokeWidth=.7, strokeDashArray=[3, 2]))
    u, api, auth, service, db = centers
    messages = [
        (u, api, 200, "POST /api/reportes"),
        (api, auth, 178, "validar sesión + CSRF"),
        (auth, api, 156, "autorizar creación"),
        (api, service, 134, "validar datos"),
        (service, db, 112, "insertar reporte"),
        (db, service, 90, "id + estado inicial"),
        (service, u, 68, "respuesta JSON 201"),
    ]
    for x1, x2, y, label in messages:
        arrow(d, x1, y, x2, y, color=TEAL if "201" in label else BLUE)
        labelx = min(x1, x2) + 3
        d.add(String(labelx, y + 4, label, fontName=BODY_FONT, fontSize=6.05, fillColor=INK))
    d.add(String(18, 5, "La autorización se verifica en el servidor, aunque la interfaz oculte las acciones no permitidas.", fontName=BODY_FONT, fontSize=6.7, fillColor=MID))
    return d

def read_tasks():
    path = ROOT / "docs" / "planificacion" / "Backlog_Jira_PreventiTI.csv"
    status = {}
    for n in range(1, 13):
        status[f"PVT-{n:03d}"] = "Completo\nsegún equipo"
    status["PVT-013"] = "Completo en esta entrega\ncommit inicial pendiente"
    status["PVT-014"] = "Completo\nsegún equipo"
    status["PVT-015"] = "No aplica\ncondicional"
    for n in range(16, 28):
        status[f"PVT-{n:03d}"] = "Completo\nsegún equipo"
    for n in range(28, 39):
        status[f"PVT-{n:03d}"] = "Planificado\nHito 3"
    tasks = []
    with path.open(encoding="utf-8-sig", newline="") as f:
        for row in csv.DictReader(f):
            summary = row.get("Summary", "")
            match = re.match(r"\[(PVT-\d+)\]\[(H\d)\]\s*(.*)", summary)
            if not match:
                continue
            key, milestone, title = match.groups()
            desc = row.get("Description", "")
            def field(label):
                m = re.search(r"(?:^|; )" + re.escape(label) + r": ([^;]*)", desc)
                return m.group(1).strip() if m else ""
            owner = field("Responsable previsto") or "Sin asignar"
            collaborators = field("Colaboradores")
            team = owner if not collaborators or collaborators.lower() == "ninguno" else owner + "\n" + collaborators
            window = field("Ventana del plan")
            tasks.append((key, title, team, window, status.get(key, "Sin estado"), milestone))
    return tasks


class PreventiDoc(BaseDocTemplate):
    def __init__(self, filename):
        super().__init__(filename, pagesize=A4, leftMargin=42, rightMargin=42, topMargin=45, bottomMargin=43,
                         title="PreventiTI — Hito 2: Avance y seguimiento", author="Equipo PreventiTI")
        frame = Frame(self.leftMargin, self.bottomMargin, self.width, self.height, id="frame", leftPadding=0,
                      rightPadding=0, topPadding=0, bottomPadding=0)
        self.addPageTemplates([
            PageTemplate(id="Cover", frames=[frame]),
            PageTemplate(id="Body", frames=[frame], onPage=self.draw_header_footer),
        ])

    def draw_header_footer(self, canvas, doc):
        canvas.saveState()
        canvas.setStrokeColor(GRID)
        canvas.setLineWidth(.5)
        canvas.line(42, PAGE_H - 31, PAGE_W - 42, PAGE_H - 31)
        canvas.setFont(BODY_FONT, 7)
        canvas.setFillColor(MID)
        canvas.drawString(42, PAGE_H - 24, "PREVENTITI  /  HITO 2")
        canvas.drawRightString(PAGE_W - 42, PAGE_H - 24, "Gestión documental · Corte 27 oct 2026")
        canvas.line(42, 31, PAGE_W - 42, 31)
        canvas.drawString(42, 19, "Documento académico · versión 2.0")
        canvas.drawRightString(PAGE_W - 42, 19, f"Página {doc.page}")
        canvas.restoreState()

    def afterFlowable(self, flowable):
        if not isinstance(flowable, Paragraph):
            return
        name = flowable.style.name
        if name not in ("HitoHeading1", "HitoHeading2"):
            return
        level = 0 if name == "HitoHeading1" else 1
        text = flowable.getPlainText()
        self.notify("TOCEntry", (level, text, self.page))
        key = f"toc-{self.page}-{level}-{re.sub(r'[^a-zA-Z0-9]+', '-', text)[:40]}"
        self.canv.bookmarkPage(key)
        try:
            self.canv.addOutlineEntry(text, key, level=level, closed=(level > 0))
        except ValueError:
            pass


def build_story():
    story = []

    # Cover
    story.append(Spacer(1, 44 * mm))
    story.append(para("GESTIÓN DOCUMENTAL  ·  PROYECTO PREVENTIVO", "CoverKicker"))
    story.append(para("HITO 2", "CoverTitle"))
    story.append(para("Avance y seguimiento", "CoverSub"))
    story.append(Spacer(1, 8 * mm))
    story.append(callout(
        "Documento actualizado del proyecto",
        "Integra el estado informado por el equipo, la documentación técnica disponible, las pruebas ejecutadas en la copia local y el plan para el Hito 3. Las cifras de infraestructura se identifican como estimaciones.",
        fill=PALE_BLUE,
    ))
    story.append(Spacer(1, 19 * mm))
    story.append(para("Versión 2.0 · 27 de octubre de 2026", "CoverMeta"))
    story.append(para("Equipo informado en la documentación del proyecto", "CoverMeta"))
    story.append(para("Ramiro Rebello · coordinación y desarrollo web<br/>Daniel Sanz · datos y lógica de negocio<br/>Ignacio de la Rosa · infraestructura, redes y seguridad", "CoverMeta"))
    story.append(Spacer(1, 11 * mm))
    story.append(para("Proyecto académico PreventiTI · inventario objetivo: 69 activos · perfiles objetivo: 40 usuarios", "Small"))
    story.append(NextPageTemplate("Body"))
    story.append(PageBreak())

    toc = TableOfContents()
    toc.levelStyles = [styles["TOC1"], styles["TOC2"]]
    story.append(para("Contenido", "TOCTitle"))
    story.append(toc)
    story.append(PageBreak())

    # 1
    story.append(heading("1. Control del documento y criterio de evidencia"))
    story.append(para("Este informe actualiza el Hito 1 para el Hito 2 de Gestión Documental. El corte corresponde al 27/10/2026. Los requisitos del Hito 1 y los archivos del proyecto se conservaron como fuentes; el estado del equipo se incorporó según la declaración recibida para esta actualización."))
    story.append(table([
        ["Campo", "Registro"],
        ["Identificador", "PreventiTI-GD-H2-2026-02"],
        ["Versión / fecha", "2.0 / 27-10-2026"],
        ["Estado", "Documento de avance para revisión y entrega del Hito 2"],
        ["Base documental", "Hito 1 aportado; backlog PVT-001 a PVT-038; memoria técnica; roles; código, dependencias y suite local"],
        ["Criterio de trazabilidad", "“Completado según equipo” indica declaración de entrega académica. Las pruebas locales se distinguen de evidencias externas no re-adjuntadas."],
    ], [116, 394]))
    story.append(heading("1.1. Qué es dato, qué es medición y qué es estimación", 2))
    story.append(table([
        ["Etiqueta", "Uso en este documento"],
        ["Informado por el equipo", "Hito 1 entregado; áreas de Programación, Base de Datos y Conectividad/Seguridad del Hito 2 entregadas; unas 80 horas por integrante."],
        ["Comprobado en la copia local", "Versiones fijadas en archivos de dependencias, configuración SQLite, suite automatizada y repositorio Git inicializado sin commits."],
        ["Estimación", "Tres VM núcleo, capacidad del host, costos de hardware, servicios y valor económico orientativo de las horas."],
        ["Pendiente de verificación", "Evidencias académicas externas no anexadas aquí, restauración real del respaldo, despliegue MariaDB de extremo a extremo y rendimiento."],
    ], [123, 387]))
    story.append(Spacer(1, 5))
    story.append(callout("Criterio de integridad documental", "Los datos inventados o simulados no se presentan como hechos. Los costos son estimaciones con fecha de consulta; las horas y entregas se atribuyen al equipo. No se deduce un desvío de calendario sin una fecha real de inicio y una línea base fechada por semana.", fill=PALE_AMBER, border=colors.HexColor("#BC8424")))

    # 2
    story.append(heading("2. Resumen ejecutivo y alcance vigente"))
    story.append(para("PreventiTI es un sistema académico de gestión preventiva de activos y reportes para un inventario de 69 activos y una población objetivo de 40 usuarios. El catálogo se reparte en 30 terminales, 10 equipos portátiles, 4 impresoras, 3 cartelerías digitales, 10 activos de seguridad/CCTV, 6 equipos de climatización IoT y 6 elementos de respaldo eléctrico. Los roles definidos son 3 administradores, 1 jefe técnico, 6 especialistas y 30 empleados."))
    story.append(table([
        ["Indicador", "Estado al corte", "Fuente"],
        ["Hito 1", "Entregado según equipo", "Declaración del equipo · 27/10/2026"],
        ["Hito 2 · Programación", "Entregado según equipo", "Declaración del equipo · evidencia fuera de esta carpeta"],
        ["Hito 2 · Base de Datos", "Entregado según equipo", "Declaración del equipo · evidencia fuera de esta carpeta"],
        ["Hito 2 · Conectividad y Seguridad", "Entregado según equipo", "Declaración del equipo · evidencia fuera de esta carpeta"],
        ["Gestión documental Hito 2", "Informe generado; control Git inicial preparado", "Repositorio local creado; commit inicial pendiente de revisión"],
        ["Esfuerzo total informado", "≈ 80 h por integrante · ≈ 240 h para 3 integrantes", "Estimación del equipo; sin planilla horaria detallada"],
        ["Pruebas automatizadas locales", "66 PHP: 698 aserciones; 24 frontend: 72 aserciones; 0 fallos", "Ejecuciones registradas el 27/10/2026"],
    ], [126, 211, 173]))
    story.append(heading("2.1. Mercado y posición del sistema", 2))
    story.append(para("PreventiTI se ubica como una solución académica a medida para el flujo acordado de inventario, reportes, mantenimiento y perfiles. GLPI publica funciones de inventario, mesa de ayuda, tickets, planificación y consumibles [1]. Snipe-IT se enfoca en administración de activos, historial y control por roles [2]. Freshservice es una opción ITSM alojada y comercial por agente [3]. La comparación es funcional y de posicionamiento; no es una evaluación de costo total ni de cumplimiento de producción."))
    story.append(table([
        ["Alternativa", "Enfoque publicado", "Diferencia de alcance de PreventiTI"],
        ["GLPI", "Inventario y gestión de servicios/solicitudes; módulos de planificación y consumibles.", "PreventiTI concentra su alcance en prevención de activos, reportes y roles definidos para 40 usuarios."],
        ["Snipe-IT", "Administración de activos, asignación, historial y permisos.", "PreventiTI además modela reportes operativos, seguimiento técnico, calendario y datos de mantenimiento."],
        ["Freshservice", "ITSM en modalidad SaaS, con planes comerciales por agente.", "PreventiTI es un prototipo propio con costo de licencia actual nulo y mayor responsabilidad de operación/seguridad del equipo."],
    ], [77, 186, 247]))
    story.append(para("Fuentes del mercado consultadas: páginas oficiales de producto; ver Referencias [1]–[3]. El sistema no se declara equivalente en madurez, soporte o certificación.", "Small"))

    # 3
    story.append(heading("3. Planificación, avance y tareas asignadas"))
    story.append(para("La planificación heredada organiza 12 semanas: Hito 1 en semanas 1–4, Hito 2 en semanas 5–9 y Hito 3 en semanas 10–12. Se mantiene esa línea base. La carpeta contiene ventanas semanales, pero no una fecha de inicio aprobada ni una serie de actualizaciones fechadas; por eso no se calcula un atraso en días."))
    story.append(table([
        ["Hito / ventana", "Trabajo previsto", "Estado actualizado", "Desvío de calendario"],
        ["H1 · semanas 1–4", "Alcance, factibilidad, requisitos, MER/DDL, esqueleto Laravel y diseño de red.", "Completo según equipo; entregado.", "No calculable sin fecha real de inicio/cierre."],
        ["H2 · semanas 5–9", "Seguimiento, datos 69/40, módulos, pruebas, UML, pfSense/VLAN/WireGuard/Nmap y validación de BD.", "Programación, BD y Conectividad/Seguridad completas según equipo. Gestión documental: este entregable en curso.", "No calculable; evidencia académica externa no re-adjunta."],
        ["H3 · semanas 10–12", "Prueba de restauración, optimización, hardening, regresión, manuales y acta de cierre.", "Planificado; tareas PVT-028 a PVT-038.", "Revisar línea base al inicio del Hito 3."],
    ], [83, 197, 147, 83]))
    story.append(heading("3.1. Registro completo del backlog con responsables", 2))
    story.append(para("Los responsables y colaboradores provienen del CSV de planificación. El estado de Hito 1 y de las áreas H2 entregadas refleja la declaración del equipo; no convierte una evidencia externa en evidencia disponible en esta carpeta. PVT-013 se declara completado con este informe; el primer commit queda como acción siguiente de control."))
    task_rows = [["ID", "Tarea", "Responsable / colaboradores", "Ventana / estado"]]
    for key, title, team, window, state, milestone in read_tasks():
        task_rows.append([
            plain(key, "TableCellSmall"),
            plain(title, "TableCellSmall"),
            plain(team, "TableCellSmall"),
            plain(window + "\n" + state, "TableCellSmall"),
        ])
    story.append(table(task_rows, [48, 238, 135, 92], font="TableCellSmall", padd=3.1))
    story.append(Spacer(1, 5))
    story.append(callout("Resumen del registro", "26 tareas de H1/H2 constan completas (según el equipo y este entregable); PVT-015 es condicional y queda como no aplicable mientras no se soliciten identidades oficiales; 11 tareas de H3 están planificadas. El commit inicial se registra como acción pendiente, sin bloquear el plan documental. El backlog no contiene asignaciones a cuentas de Jira; se muestran nombres previstos.", fill=PALE_GREEN, border=TEAL))

    story.append(heading("3.2. Desvíos, causas y acciones correctivas", 2))
    story.append(table([
        ["ID", "Desvío / evidencia", "Impacto", "Acción correctiva", "Responsable / fecha"],
        ["D-01", "La matriz local anterior mostraba trabajos parciales, mientras el equipo reporta H1 y las áreas de H2 entregadas.", "Trazabilidad académica; no implica que la entrega haya fallado.", "Alinear la matriz final con las evidencias ya entregadas; adjuntar o enlazar actas/capturas al paquete final.", "Daniel + cada líder de área / antes de cerrar H2"],
        ["D-02", "La carpeta no tenía Git. Se creó repo local en rama main el 27/10/2026; aún sin commits ni remoto.", "Sin historial recuperable ni revisión compartida.", "Revisar archivos y exclusiones, registrar commit inicial revisado y definir remoto si el equipo lo usa.", "Ramiro + todo el equipo / siguiente sesión"],
        ["D-03", "El entorno local usa SQLite; MariaDB es el motor de destino. La carpeta no conserva salida de validación MariaDB de extremo a extremo.", "Riesgo de diferencia de tipos, restricciones y consultas al desplegar.", "Ejecutar carga y suite de integración contra copia aislada MariaDB; guardar versión, logs y conteos.", "Daniel + Ramiro / H3"],
        ["D-04", "El servicio local genera y verifica backup SQLite; no se encontró procedimiento de restauración implementado ni calendario operativo.", "Recuperación real no está demostrada.", "Programar copias, guardar copia externa cifrada y probar restauración aislada con registro del tiempo.", "Daniel / H3"],
        ["D-05", "La configuración Nginx histórica apunta a PHP-FPM 8.2, por debajo del requisito PHP ≥8.3 de Laravel 13.", "Bloqueo potencial de despliegue.", "Alinear la VM a PHP-FPM ≥8.3 con paquete vigente y verificar extensiones, build y healthcheck.", "Ramiro / antes de despliegue"],
        ["D-06", "Los archivos disponibles no representan necesariamente el paquete externo de entrega ni el estado final mostrado en clase.", "Puede faltar vínculo entre código, evidencia y declaración de cierre.", "Etiquetar cada artefacto con versión/fecha y dejar una matriz de evidencia firmada por área.", "Cada responsable / cierre H2"],
        ["D-07", "El script histórico de MariaDB conserva una contraseña predeterminada incrustada como alternativa a DB_PASSWORD.", "Riesgo de divulgar una credencial de ejemplo o reutilizarla por error.", "Eliminar el valor por defecto; usar un archivo de credenciales con permisos restringidos/secretos del entorno y rotar cualquier clave que se haya usado.", "Daniel + Ignacio / antes del primer commit o despliegue"],
    ], [32, 137, 79, 178, 87], font="TableCellSmall", padd=3))
    story.append(para("No se registran en los archivos locales fechas reales de incumplimiento, incidentes ni costo incurrido. Los puntos D-01 a D-07 son desvíos de trazabilidad, despliegue o control observados al corte, no eventos de seguridad materializados.", "Small"))

    # 4
    story.append(heading("4. Cambios realizados y actualización documental"))
    story.append(para("El registro se basa en fechas presentes en los artefactos del proyecto, no en un historial Git previo (ese historial no existía en esta carpeta). La tabla separa decisiones y cambios que el repositorio local documenta de las entregas que el equipo reportó."))
    story.append(table([
        ["Fecha", "Cambio documentado", "Razón / efecto"],
        ["15/09/2026", "Fecha de creación del PDF de Hito 1 aportado.", "Línea base documental inicial."],
        ["22/09/2026", "Migración del catálogo al alcance oficial: 69 activos y 40 usuarios; conciliación de datos y conservación del historial.", "Alinear el prototipo con el alcance vigente; preservar relaciones históricas y trazabilidad."],
        ["24/09/2026", "Ajustes de contrato de base y flujo de reportes según migración documentada.", "Alinear Laravel y el esquema de datos."],
        ["01/10/2026", "Se registra aprobación de la arquitectura de Red/Seguridad vigente: VLAN, firewall y VPN WireGuard.", "Conservar configuración aprobada sin modificar direcciones o reglas evaluadas."],
        ["04/10/2026", "Se incorpora persistencia de posiciones del mapa de activos.", "Conectar mapa con estado guardado en servidor."],
        ["20–22/10/2026", "Se consolida memoria técnica, roles, CRUD/API de activos y documentación de backup SQLite; se actualiza el inventario de pendientes.", "Describir código y pruebas locales actuales; detectar diferencias entre el plan inicial y el despliegue."],
        ["21/10/2026", "Se documentan perfiles, restricciones de acceso y cuentas de demostración.", "Definir autorización por perfil y limitar exposición de datos."],
        ["27/10/2026", "Se inicializa Git local en main y se prepara la actualización Hito 2.", "Crear control de cambios y consolidar seguimiento, riesgos, arquitectura y métricas."],
    ], [67, 220, 225]))
    story.append(heading("4.1. Ajustes frente al Hito 1", 2))
    story.append(table([
        ["Apartado", "Ajuste incorporado", "Motivo"],
        ["Numeración y estructura", "Índice y apartados con numeración consecutiva; listas corregidas; tablas con IDs estables.", "Evitar referencias duplicadas y mejorar evaluación/seguimiento."],
        ["Seguimiento", "Estados por tarea, responsable, ventana, desviaciones y acciones correctivas.", "Registrar ejecución, no solo describir un método."],
        ["Riesgos", "Matriz de identificación separada de matriz de monitoreo; probabilidad e impacto explícitos.", "Separar evaluación inicial de seguimiento de señales, respuesta y riesgo residual."],
        ["Factibilidad", "Versiones y roles definidos; costo académico real distinguido del escenario de implementación estimado.", "Actualizar tecnologías y restricciones operativas."],
        ["Respaldo", "Alcance actual, validaciones existentes y procedimiento propuesto de copia/retención/restauración.", "El backup no demuestra recuperación hasta probarla."],
        ["Calidad y pruebas", "Resultados cuantitativos PHP/JS, requisitos no medidos y plan de regresión.", "No atribuir cobertura ni rendimiento sin medición."],
    ], [103, 247, 162]))

    # 5
    story.append(heading("5. Diseño general del sistema y factibilidad técnica"))
    story.append(para("La aplicación actual se estructura como un monolito Laravel con API JSON, Eloquent, servicios de dominio, Form Requests, políticas de autorización, Blade y módulos JavaScript. SQLite es la base activa de la copia local. MariaDB es el motor de destino definido por el proyecto; se debe tratar como entorno separado hasta que se guarde evidencia de despliegue y pruebas reproducibles."))
    story.append(architecture_diagram())
    story.append(heading("5.1. Tecnologías, versiones y función", 2))
    story.append(table([
        ["Área", "Versión comprobada / propuesta", "Uso y estado"],
        ["Desarrollo servidor", "PHP CLI 8.4.23; Laravel 13.30.1; Composer 2.10.1", "Local, versiones comprobadas en el entorno y composer.lock. Laravel 13 requiere PHP 8.3 o posterior."],
        ["Interfaz", "Node.js 24.18.0; npm 11.16.0; Tailwind 3.4.17; Chart.js 4.6.1; Lucide 1.61.0; Plus Jakarta Sans 5.3.0; JetBrains Mono 5.3.0", "Dependencias fijadas en package-lock.json; build local, recursos servidos desde el proyecto."],
        ["Mapa", "Three.js en public/js/vendor/three; versión de release no registrada en package-lock", "Librería vendorizada para el plano; dejar anotado número de revisión antes de H3."],
        ["Base actual", "SQLite en archivo", "Desarrollo local y ejecución de pruebas con SQLite en memoria."],
        ["Base destino", "MariaDB 10.11.19 LTS al corte; actualizar parche al instalar", "Propuesta compatible con el DDL declarado 10.11+; restringir 3306 a la VM de aplicación."],
        ["VM de aplicación", "Ubuntu Server 24.04.5 LTS; Nginx 1.24.0-2ubuntu7.18; PHP-FPM 8.3.6-0ubuntu0.24.04.11", "Paquetes al corte en Ubuntu 24.04 [23][24]. Instalar último parche aprobado; Laravel 13 requiere PHP ≥8.3."],
        ["Virtualización", "Proxmox VE 9.2-1", "Propuesta para host de virtualización; actualizar al parche soportado vigente al instalar."],
        ["Firewall/VPN", "pfSense CE 2.9.0; WireGuard según paquete soportado", "Propuesta de VM de borde; la versión exacta de paquete WireGuard se fija al instalar y se registra en acta."],
        ["Gestión del proyecto", "Backlog CSV PVT-001–038 + este informe; Git 2.54.0 local", "No se detectó instancia Jira ni remoto Git asociado. El repositorio está inicializado pero sin commits."],
        ["Pruebas", "PHPUnit 12.5.34 vía php artisan test; Node test runner (node --test)", "PHPUnit fijado en composer.lock; frontend ejecutado con Node.js 24.18.0."],
    ], [96, 160, 256], font="TableCellSmall", padd=3.5))
    story.append(para("El inventario del software registra versiones observadas al corte. Three.js está vendorizado en public/js/vendor/three, fuera de package-lock.json; el número de revisión debe añadirse al inventario de dependencias antes del despliegue. En Ubuntu 24.04.5, Nginx se debe instalar desde el repositorio de seguridad de la distribución y registrar el paquete efectivo al momento de desplegar.", "Small"))
    story.append(heading("5.2. Capacidad virtual estimada", 2))
    story.append(para("Para una instalación académica operable y segmentada se proyectan 3 VM núcleo. Es un dimensionamiento inicial para 40 usuarios y 69 activos, no una medición de consumo ni la confirmación del número de VM que tiene hoy el equipo."))
    story.append(table([
        ["VM", "Responsabilidad", "CPU / RAM / disco inicial", "Red"],
        ["VM-01", "pfSense CE: routing, firewall entre VLAN y WireGuard para administración", "2 vCPU · 4 GB RAM · 40 GB", "Interfaces/VLAN según topología aprobada; acceso administrativo solo desde VPN."],
        ["VM-02", "Ubuntu + Nginx + PHP-FPM 8.3 + Laravel", "4 vCPU · 8 GB RAM · 100 GB", "HTTPS de clientes; conexión a VM-03 por red de servidores."],
        ["VM-03", "Ubuntu + MariaDB 10.11 LTS", "4 vCPU · 8 GB RAM · 250 GB", "3306 únicamente desde VM-02 y administración autorizada."],
    ], [50, 171, 132, 159], font="TableCellSmall", padd=3.5))
    story.append(para("Host propuesto: CPU de 8 núcleos físicos, 64 GB ECC, 2 discos SSD de 2 TB en espejo, NIC con VLAN y UPS. NAS independiente para la copia; no se cuenta como VM. Un host único conserva un punto único de falla, por lo que no se considera alta disponibilidad empresarial.", "Small"))
    story.append(heading("5.3. Requisitos funcionales y de calidad que guían la validación", 2))
    story.append(table([
        ["Requisito", "Criterio verificable", "Estado"],
        ["RF-01 · Identidad y rol", "Usuario autenticado accede solo a rutas y acciones de su perfil; autorización comprobada en servidor.", "Automatizado localmente; ver apartado 7."],
        ["RF-02 · Inventario", "Alta, consulta, edición, filtros y baja lógica de activos por personal autorizado.", "Área H2 declarada entregada; CRUD local descrito y probado."],
        ["RF-03 · Reportes", "Empleado crea y consulta reportes propios; jefe asigna y transiciona; técnico ve propios/asignados.", "Área H2 declarada entregada; conservar evidencia completa por rol."],
        ["RF-04 · Operación preventiva", "Mantenimiento, consumibles y calendario actualizan servidor y auditoría.", "Entregado según equipo; contrastar con copia exacta de entrega para resolver diferencias del snapshot local."],
        ["RNF-01 · Rendimiento", "Objetivo H1: carga/consulta menor a 2 s en condiciones definidas.", "No se registró medición comparable."],
        ["RNF-02 · Recuperación", "Objetivo de diseño: recuperar dentro de 15 min.", "No se ha evidenciado restauración cronometrada."],
        ["RNF-03 · Seguridad", "Sesión individual, hash de contraseñas, CSRF, rate limit, políticas y red segmentada.", "Controles presentes en código/documentación; revisión final antes de producción."],
    ], [98, 264, 150], font="TableCellSmall", padd=3.5))

    # 6 UML
    story.append(heading("6. Diagramas UML"))
    story.append(para("Los diagramas resumen el diseño lógico vigente. El diagrama de clases simplifica relaciones para su lectura y no reemplaza el MER, migraciones ni DDL.", "Small"))
    story.append(heading("6.1. Casos de uso", 2))
    story.append(use_case_diagram())
    story.append(Spacer(1, 4))
    story.append(table([
        ["Actor", "Casos de uso principales", "Restricción"],
        ["Empleado", "Autenticarse; crear reporte; consultar sus reportes.", "No ve inventario completo, directorio, costos ni auditoría."],
        ["Especialista", "Consultar reportes propios/asignados; ver inventario; mantenimiento/consumibles; tareas asignadas.", "Sin directorio, costos, auditoría ni cambio de estado reservado al jefe."],
        ["Jefe técnico", "Asignar técnicos; avanzar reportes; revisar métricas e infraestructura; programar tareas.", "No administra usuarios ni alta/edición/baja lógica general de activos."],
        ["Administrador", "Administrar usuarios/roles, activos, informes, costos, auditoría e infraestructura.", "No asigna reportes ni altera sus estados según la matriz de roles vigente."],
    ], [80, 244, 188], font="TableCellSmall", padd=3))
    story.append(PageBreak())
    story.append(heading("6.2. Diagrama de clases conceptual", 2))
    story.append(class_diagram())
    story.append(Spacer(1, 7))
    story.append(callout("Nota de modelado", "La base local registra usuarios, activos, reportes, mantenimientos, consumibles y tareas de calendario. Las asociaciones representadas expresan el dominio; cardinalidades físicas y diferencias SQLite/MariaDB deben leerse en el MER y el esquema consolidado.", fill=PALE))
    story.append(heading("6.3. Secuencia: creación de reporte", 2))
    story.append(sequence_diagram())

    # 7
    story.append(heading("7. Factibilidad operativa y control de acceso"))
    story.append(para("La factibilidad operativa se apoya en cuentas individuales y cuatro perfiles. La interfaz reduce menús visibles, y servidor, rutas y políticas deben volver a aplicar los mismos límites. El usuario empleado accede a campos mínimos del activo para asociar una falla sin consultar su ficha completa."))
    story.append(table([
        ["Capacidad / información", "Admin", "Jefe técnico", "Especialista", "Empleado"],
        ["Perfil, sesión y cierre propios", "Sí", "Sí", "Sí", "Sí"],
        ["Crear reportes", "Sí", "Sí", "Sí", "Sí"],
        ["Consultar reportes", "Todos", "Todos", "Propios/asignados e historial", "Solo propios"],
        ["Asignar técnicos y cambiar estados", "No", "Sí", "No", "No"],
        ["Alertas de reportes", "No", "Sí", "No", "No"],
        ["Inventario completo y mapa", "Sí", "Sí", "Sí", "No"],
        ["Alta/edición/baja lógica de activos", "Sí", "No", "No", "No"],
        ["Mantenimiento y consumibles", "Sí", "Sí", "Sí", "No"],
        ["Agenda", "Todas", "Todas", "Solo asignadas", "No"],
        ["Programar tareas", "Sí", "Sí", "No", "No"],
        ["Estadísticas/costos/auditoría/infraestructura", "Sí", "Sí", "No", "No"],
        ["Directorio de usuarios y roles", "Sí", "No", "No", "No"],
        ["Activo mínimo para crear reporte", "Sí", "Sí", "Sí", "Código/nombre/ubicación"],
    ], [202, 62, 72, 86, 88], font="TableCellSmall", padd=3.2))
    story.append(heading("7.1. Factibilidad y restricciones de operación", 2))
    story.append(table([
        ["Aspecto", "Condición de operación"],
        ["Usuarios", "40 perfiles objetivo; 3 administradores, 1 jefe, 6 especialistas y 30 empleados. Los datos de demostración se mantienen identificados como simulados."],
        ["Acceso y autenticación", "Sesión individual; hash de contraseña; regeneración de sesión; limitación de intentos; protección CSRF y políticas de servidor documentadas."],
        ["Datos sensibles", "No almacenar contraseñas legibles en repositorio. Evitar cédulas, correos personales y datos de personal real en la carga académica."],
        ["Dependencias operativas", "Despliegue compatible con PHP ≥8.3, base destino y certificados; respaldos y registro de eventos; persona responsable de actualizaciones."],
        ["Restricción vigente", "La copia local en SQLite y el proyecto académico no acreditan capacidad, disponibilidad ni soporte para producción."],
    ], [103, 407]))

    # 8
    story.append(heading("8. Factibilidad legal y marco de referencia"))
    story.append(para("El proyecto se implementa en Uruguay. La Ley N.º 18.331 establece principios y obligaciones sobre datos personales, incluida seguridad y responsabilidad; los artículos 10 y 12 son relevantes para proteger la información y adoptar medidas desde el diseño [4][5]. El Decreto 414/009 reglamenta aspectos de la ley [6]. Antes de operar con personas y activos reales, el responsable institucional debe determinar finalidad, base jurídica, avisos, acceso, conservación, derechos de titulares y obligaciones de registro que correspondan."))
    story.append(table([
        ["Referencia", "Aplicación al proyecto", "Alcance de este Hito"],
        ["Ley 18.331, art. 10", "Seguridad y confidencialidad; acceso limitado por rol; protección de copias y credenciales.", "Diseño/controles a revisar; no es dictamen de cumplimiento."],
        ["Ley 18.331, art. 12", "Responsabilidad y protección en el tratamiento desde el diseño/operación.", "Mantener trazabilidad, minimización y proceso de respuesta a solicitudes/incidentes."],
        ["Decreto 414/009", "Reglamentación sobre tratamiento y bases de datos personales.", "Verificar el contexto institucional y las obligaciones aplicables antes de producción."],
        ["Familia ISO/IEC 27000", "Sistema de gestión de seguridad, controles y gestión de riesgos (p. ej. 27001:2022, 27005:2022 y 27002:2022) [7][8][22].", "Marco de orientación; no se afirma certificación."],
        ["Familia ISO/IEC 25000 (SQuaRE)", "Calidad del producto, medición y evaluación; ISO/IEC 25010:2023 define el modelo de calidad [9].", "Usar características para revisar requisitos y pruebas; no se afirma certificación."],
    ], [112, 257, 141], font="TableCellSmall", padd=3.4))
    story.append(heading("8.1. Medidas operativas recomendadas", 2))
    story.append(table([
        ["Control", "Medida"],
        ["Minimización", "Usar solo datos necesarios para identificar usuario, activo y estado de tareas."],
        ["Acceso", "Aplicar mínimo privilegio por rol; revisar altas/bajas y desactivar cuentas que ya no correspondan."],
        ["Retención", "Definir períodos de retención y eliminación con la institución; documentar resguardos y borrado seguro."],
        ["Transmisión/almacenamiento", "HTTPS, cifrado de dispositivos y copias externas, manejo de secretos fuera de Git y permisos restringidos."],
        ["Incidentes y derechos", "Registrar solicitud/incidente, responsable, plazos y escalamiento institucional; no improvisar un canal público."],
    ], [112, 398]))

    # 9
    story.append(heading("9. Calidad del sistema, métricas y plan de pruebas"))
    story.append(para("Se utilizan características de calidad de la familia ISO/IEC 25000 como guía: adecuación funcional, eficiencia de desempeño, compatibilidad, capacidad de interacción, fiabilidad, seguridad de la información, mantenibilidad, flexibilidad y seguridad operacional. La verificación cuantitativa disponible mide pruebas automatizadas, no cobertura de código ni desempeño bajo carga."))
    story.append(heading("9.1. Métricas al corte", 2))
    story.append(table([
        ["Métrica", "Resultado", "Interpretación / límite"],
        ["Pruebas PHP ejecutadas", "66 pasaron; 0 fallaron; 698 aserciones", "Suite local ejecutada con php artisan test; la copia usa SQLite de pruebas. No se reporta desglose por tipo ni cobertura."],
        ["Pruebas frontend ejecutadas", "24 pasaron; 0 fallaron; 72 aserciones", "Node test runner; se observó advertencia no bloqueante MODULE_TYPELESS_PACKAGE_JSON."],
        ["Tasa de éxito de esta ejecución", "100% en ambas suites", "66/66 y 24/24 en la sesión informada; no demuestra ausencia de defectos."],
        ["Aserciones totalizadas", "770 (698 PHP + 72 JS)", "Conteo bruto; no es métrica de cobertura."],
        ["Cobertura de código", "No medida", "No hay reporte de cobertura disponible."],
        ["Rendimiento", "No medido en esta actualización", "Objetivo heredado de <2 s debe medirse con entorno, datos y carga definidos."],
        ["Recuperación", "No cronometrada", "Objetivo <15 min requiere restauración real y evidencia."],
        ["Defectos abiertos", "No hay registro consolidado de incidencias", "No equivale a afirmar que no hubo defectos."],
    ], [119, 133, 258], font="TableCellSmall", padd=3.2))
    story.append(heading("9.2. Plan de pruebas", 2))
    story.append(table([
        ["ID", "Nivel / alcance", "Criterio de aceptación", "Resultado disponible"],
        ["TP-01", "Pruebas de unidad: servicios, políticas y reglas de negocio; datos aislados.", "Casos válidos y negativos para roles, límites, transiciones, validaciones y alertas.", "Plan definido; la suite local medida no aporta un reporte separado de tests/Unit."],
        ["TP-02", "Pruebas PHP de feature/API: auth, RBAC, CRUD, reportes, mantenimiento, backup.", "HTTP/servicios devuelven estado y persistencia esperados; acciones no autorizadas denegadas.", "Ejecutadas junto con la suite PHP: 66 tests, 698 aserciones, 0 fallos."],
        ["TP-03", "Pruebas frontend JS: filtros, componentes, mapa, datos y estados de pantalla.", "Módulos completan escenarios y manejan respuestas y errores previstos.", "24 tests, 72 aserciones, 0 fallos."],
        ["TP-04", "Integración MariaDB, migraciones, FK y conteos 69/40.", "Carga aislada reproducible; consultas y restricciones verificadas en motor de destino.", "Hito de BD declarado entregado; salida de integración no re-adjunta en esta carpeta."],
        ["TP-05", "Red/VPN: VLAN, firewall, Nmap y tráfico permitido/bloqueado.", "Evidencia fechada coherente con topología aprobada y peers autorizados.", "Hito de Conectividad/Seguridad declarado entregado; evidencias externas no copiadas aquí."],
        ["TP-06", "Backup y recuperación completa", "Integridad verificada, app recuperada y duración documentada (<15 min objetivo).", "Pendiente de restauración local demostrable; incluir en H3."],
        ["TP-07", "Rendimiento y carga", "Latencias y errores medidos con carga definida; objetivo heredado <2 s.", "No medido."],
    ], [42, 148, 182, 138], font="TableCellSmall", padd=3))
    story.append(para("Ejecución local informada: 27/10/2026. La suite PHP incluye pruebas de feature en los archivos disponibles; no se afirma que las 66 sean pruebas unitarias puras. El equipo declara entregado el Hito 2 de Programación y reporta que sus evidencias se presentaron en el mismo formato que Hito 1.", "Small"))

    # 10
    story.append(heading("10. Gestión de riesgos"))
    story.append(para("Escala cualitativa: probabilidad (P) e impacto (I) de 1 a 5. Puntaje inicial = P×I. Bandas: 1–4 bajo; 5–9 medio; 10–16 alto; 17–25 crítico. Los puntajes son una evaluación de planificación al 27/10/2026, no un registro de incidentes. El impacto prioriza confidencialidad, integridad, disponibilidad, continuidad y cumplimiento."))
    story.append(heading("10.1. Matriz de identificación de riesgos", 2))
    story.append(table([
        ["ID", "Riesgo / causa", "P", "I", "P×I / nivel", "Tratamiento preventivo", "Responsable"],
        ["R-01", "Acceso indebido o exposición de datos por permisos mal configurados o credencial comprometida.", "2", "5", "10 · Alto", "RBAC servidor, CSRF, hashes, limitar intentos y revisar auditoría.", "Ramiro"],
        ["R-02", "Pérdida de información si el único respaldo está en el mismo host o no se puede restaurar.", "3", "5", "15 · Alto", "Copia externa cifrada, comprobación SHA-256 y simulacro de restauración.", "Daniel"],
        ["R-03", "Incompatibilidad SQLite/MariaDB en tipos, FK, índices o consultas.", "4", "4", "16 · Alto", "Prueba aislada MariaDB, carga controlada y reconciliación de esquema.", "Daniel + Ramiro"],
        ["R-04", "Falta de trazabilidad entre código en carpeta, evidencia externa y estados informados.", "4", "4", "16 · Alto", "Versionar, relacionar prueba–requisito–artefacto y guardar acta por área.", "Todo el equipo"],
        ["R-05", "Corrupción/inconsistencia semántica entre activo referenciado e histórico de reportes.", "3", "4", "12 · Alto", "Conciliar códigos, IDs e historial en copia de trabajo sin reescribir registros silenciosamente.", "Daniel"],
        ["R-06", "Caída del único host de virtualización o de su almacenamiento.", "3", "5", "15 · Alto", "UPS, repuestos, NAS separado y plan de recuperación; aceptar SPOF si no hay segundo host.", "Ignacio"],
        ["R-07", "Exposición de claves, bases o credenciales: archivos privados o contraseña predeterminada en script histórico.", "3", "5", "15 · Alto", "Revisar .gitignore; quitar el valor por defecto del script; excluir .env, SQLite privada, backups, config VPN y credenciales.", "Ramiro + Daniel"],
        ["R-08", "Pérdida de disponibilidad/permisos por divergencia de rama o cambio no revisado.", "3", "3", "9 · Medio", "Ramas por tarea, revisión por compañero, etiquetas y respaldo antes de actualizar.", "Todo el equipo"],
    ], [34, 143, 24, 24, 61, 160, 64], font="TableCellSmall", padd=2.8))
    story.append(heading("10.2. Matriz de monitoreo y seguimiento de riesgos", 2))
    story.append(para("La matriz siguiente registra señales de seguimiento y respuesta. Estado “abierto” significa que la acción o verificación sigue pendiente; no afirma que el riesgo se haya materializado. El riesgo residual es objetivo, condicionado a completar el tratamiento."))
    story.append(table([
        ["ID", "Indicador / disparador", "Control actual observado", "Acción, dueño y revisión", "Residual objetivo"],
        ["R-01", "Alta no prevista; intento repetido; usuario consulta datos fuera de su perfil.", "Políticas, middleware, CSRF, sesiones y límites documentados; suite PHP pasó.", "Revisar logs y pruebas negativas por rol antes de H3. Ramiro.", "P=1, I=5 · 5 medio"],
        ["R-02", "Copia diaria ausente, hash distinto o falla en simulacro de recuperación.", "Servicio SQLite produce ZIP, manifiesto, hash e integridad; no hay restore/schedule demostrado.", "Definir retención, destino externo cifrado y probar restore mensual. Daniel.", "P=2, I=5 · 10 alto"],
        ["R-03", "Migración falla, conteo ≠69/40, FK o consulta devuelve resultado distinto.", "SQLite local y DDL MariaDB documentado; BD H2 declarada entregada por equipo.", "Registrar salida de carga de DB destino y fix de diferencias. Daniel + Ramiro.", "P=2, I=4 · 8 medio"],
        ["R-04", "Una tarea cerrada carece de artefacto, fecha o responsable de evidencia.", "CSV tiene responsable previsto; repo Git existe sin commit.", "Adjuntar evidencia de áreas y crear primer commit revisado. Todo el equipo.", "P=2, I=3 · 6 medio"],
        ["R-05", "Reporte/mantenimiento apunta a código de activo diferente al declarado.", "Inconsistencias listadas en documentación de alcance; historial preservado.", "Verificar cada caso contra fuente y anexar plan de conciliación; no corregir sin aprobación. Daniel.", "P=1, I=4 · 4 bajo"],
        ["R-06", "Alarma de UPS, disco degradado, host sin respuesta o NAS desconectado.", "Host/VM/NAS son sizing propuesto; no hay métricas de operación aportadas.", "Revisar salud de hardware y definir recuperación de host. Ignacio.", "P=2, I=5 · 10 alto"],
        ["R-07", "Git status muestra .env, dump, backup, clave o configuración con secretos; script conserva contraseña de ejemplo.", ".gitignore excluye archivos privados y DB; el valor por defecto del script sigue pendiente.", "Quitar contraseña incrustada y revisar staging antes del primer commit. Ramiro + Daniel.", "P=1, I=5 · 5 medio"],
        ["R-08", "Cambio se incorpora a main sin revisión o no es posible restaurar versión previa.", "Repositorio local en main sin commits/remoto.", "Aplicar convención de ramas y revisión; etiquetar hito. Todo el equipo.", "P=1, I=3 · 3 bajo"],
    ], [32, 112, 127, 160, 55], font="TableCellSmall", padd=2.8))

    # 11
    story.append(PageBreak())
    story.append(heading("11. Respaldo y economía"))
    story.append(heading("11.1. Estado actual y procedimiento de respaldo", 2))
    story.append(para("La aplicación local incluye BackupService para SQLite en archivo y archivos bajo storage/app. Genera un ZIP privado con instantánea transaccional, manifiesto, SHA-256 e integridad SQLite; confirma que los ficheros no hayan cambiado durante la copia. No incluye código ni secretos del servidor. El servicio no implementa restauración ni rotación programada. El script histórico para MariaDB es una propuesta y no evidencia de un cron en ejecución."))
    story.append(table([
        ["Etapa", "Procedimiento definido", "Estado"],
        ["1. Preparar", "Identificar motor, instancia y carpeta de destino; confirmar espacio; restringir acceso a operador autorizado.", "Definido para el procedimiento."],
        ["2. Crear", "SQLite: usar BackupService y archivar database.sqlite + storage/app. MariaDB: dump consistente con --single-transaction y copiar archivos necesarios.", "SQLite implementado; MariaDB script/documentación sin ejecución local revalidada."],
        ["3. Verificar", "Comprobar apertura/estructura, integridad de DB, manifiesto, tamaño y SHA-256. Registrar fecha, operador y resultado.", "Verificación del ZIP SQLite implementada."],
        ["4. Proteger y copiar", "Cifrar copia en tránsito/almacenamiento; transferir a NAS separado del host y, si hay autorización, ubicación externa.", "Destino externo/cifrado programado; no demostrado."],
        ["5. Retener", "Propuesta: 14 copias diarias, 4 semanales y 6 mensuales, revisada con capacidad/política institucional.", "Política propuesta; no programada actualmente."],
        ["6. Restaurar", "Restaurar en VM aislada; verificar integridad, login, conteos, archivos y un flujo de lectura; medir tiempo y guardar acta.", "Pendiente de prueba; objetivo <15 min heredado no medido."],
    ], [72, 338, 76], font="TableCellSmall", padd=3.5))
    story.append(heading("11.2. Presupuesto estimativo de implementación", 2))
    story.append(para("El funcionamiento académico actual declara una inversión en efectivo de USD 0: se utilizan PCs disponibles y trabajo del equipo. La tabla siguiente es otro escenario: una instalación propia con un host para virtualización, las 3 VM propuestas, almacenamiento de copias, red y UPS. No es una cotización ni una compra efectuada. Valores consultados o estimados a octubre de 2026; validar modelo, stock, impuestos, garantía y precio final con proveedores."))
    story.append(table([
        ["Componente", "Base de cálculo", "USD estimado", "Fuente / nota"],
        ["Host Proxmox", "Servidor 8 núcleos, 64 GB ECC, espejo 2×2 TB SSD, NIC con VLAN.", "2,500–3,500", "Estimación de especificación; no se encontró cotización local equivalente."],
        ["NAS y discos", "NAS 4 bahías ~641 + 2× disco NAS 8 TB ~594 c/u; espejo de capacidad útil aproximada 8 TB.", "1,829", "Precios publicados, sujetos a stock/confirmación [10][11]."],
        ["Switches administrables", "48 puertos PoE ~880.50 + segundo 48p administrable ~420.12.", "1,301", "Referencias locales de catálogo/listado [12][13]; validar modelo y presupuesto de puertos."],
        ["UPS", "1500 VA / 900 W.", "132", "Listado local consultado [14]."],
        ["Rack, cableado y transceptores", "Margen de instalación, patch panel/cableado y accesorios.", "300–800", "Provisión presupuestaria; confirmar relevamiento de sitio."],
        ["Subtotal de hardware", "Suma aproximada de partidas anteriores.", "6,062–7,562", "Estimación antes de contingencia."],
        ["Contingencia", "15% por variación, traslado, accesorios e impuestos no confirmados.", "909–1,134", "Reserva estimativa; no es cotización fiscal."],
        ["Software núcleo", "Ubuntu, Laravel, PHP, Nginx, MariaDB Community, pfSense CE, Git y runners de prueba.", "0 de licencia", "Licencias de código abierto; soporte comercial y administración no incluidos."],
        ["Respaldo externo opcional", "1 TB en servicio S3 compatible al precio publicado de USD 6.95/TB/mes.", "≈83/año", "Consumo fijo aproximado; transacciones/egreso/retención pueden aumentar el costo [15]."],
        ["Horas de equipo", "3 personas × ≈80 h informadas = ≈240 h. Valor de referencia ilustrativo USD 15–40/h.", "3,600–9,600", "No facturado ni pagado; costo económico de tiempo, tasa no pactada [16]."],
    ], [95, 211, 74, 106], font="TableCellSmall", padd=3.1))
    story.append(Spacer(1, 4))
    story.append(callout("Resumen económico", "Inversión de caja del proyecto académico informada: USD 0. Una instalación propia de pequeña escala se estima en USD 7,000–8,700 de infraestructura, con una contingencia del 15%, antes de servicios externos y sin comprar nuevamente las PCs existentes. Añadiendo 240 horas valorizadas solo como referencia y un año de 1 TB de respaldo externo, el costo económico orientativo sería de unos USD 10,700–18,400. No incluye soporte contratado, segundo host para alta disponibilidad, impuestos/instalación confirmados ni renovación del parque de PCs.", fill=PALE_GREEN, border=TEAL))
    story.append(para("La estimación de host, rack y horas no es precio observado de proveedor. Precios locales de NAS, discos, switch y UPS corresponden a publicaciones puntuales; pueden cambiar o estar sin stock. Los puestos/PCs actuales se consideran ya disponibles, según la situación informada.", "Small"))

    # 12
    story.append(heading("12. Control de versiones, cierre del Hito 2 y siguientes pasos"))
    story.append(heading("12.1. Estado del repositorio y política propuesta", 2))
    story.append(para("Se inicializó un repositorio Git local en la rama main el 27/10/2026. No hay commit inicial, historial previo ni remoto configurado. El espacio de trabajo muestra archivos sin seguimiento. El .gitignore excluye bases locales, respaldos, configuraciones privadas y credenciales. El script histórico de MariaDB aún contiene una contraseña predeterminada: corregirla y revisar el staging antes del primer commit."))
    story.append(table([
        ["Elemento", "Convención"],
        ["Ramas", "main (entregas revisadas); develop (integración si el equipo adopta esa rama); feature/PVT-###-tema; fix/PVT-###-tema; docs/PVT-###-tema."],
        ["Commits", "Incluir ticket y tipo: PVT-### feat: ..., fix: ..., docs: ..., test: ...; un cambio verificable por commit."],
        ["Revisión", "Un integrante distinto al autor revisa el cambio; ejecutar suite pertinente; actualizar documentación y dejar resultado."],
        ["Etiquetas", "hito-1-v1.0.0, hito-2-v2.0.0 y futuras versiones; no crear etiqueta de entrega hasta revisar el contenido."],
        ["Datos excluidos", ".env, bases SQLite operativas, respaldos, claves, credenciales, configuraciones WireGuard y archivos privados; no versionar dumps reales. Eliminar primero la contraseña literal del script histórico."],
        ["Respaldo Git", "Git no reemplaza backup de datos. Guardar clones/remoto autorizado y copias cifradas por separado."],
    ], [103, 407]))
    story.append(heading("12.2. Prioridades de Hito 3", 2))
    story.append(table([
        ["Prioridad", "Tareas", "Salida de cierre"],
        ["1 · Recuperación", "PVT-030", "Restauración aislada de BD/archivos, conteos, prueba funcional y duración."],
        ["2 · Integración y calidad", "PVT-028, 029, 031, 032, 033", "Validación MariaDB, integridad, autorización, hardening y regresión completa."],
        ["3 · Red y operación", "PVT-034, 035", "Monitoreo, respuesta a incidentes y evidencias TLS/VPN/firewall."],
        ["4 · Entrega final", "PVT-036, 037, 038", "Manuales, presentación, matriz de cumplimiento, lecciones aprendidas y acta."],
        ["Trazabilidad", "PVT-013 y cierre de H2", "Primer commit revisado, evidencia por área y actualización del backlog con fecha/responsable."],
    ], [93, 119, 298]))
    story.append(heading("12.3. Conclusión", 2))
    story.append(para("El estado que informa el equipo es consistente con un Hito 2 técnico ya entregado en Programación, Base de Datos y Conectividad/Seguridad, con aproximadamente 240 horas acumuladas. La copia disponible ofrece implementación Laravel 13, una suite automatizada aprobada y documentación de roles, arquitectura y respaldo SQLite. Para que el paquete de Gestión Documental sea reproducible, debe cerrarse la trazabilidad de las evidencias, registrar el primer estado versionado y completar la prueba de restauración y la validación del destino MariaDB antes de afirmar preparación para operación real."))

    # References
    story.append(heading("Referencias"))
    story.append(para("Fuentes del proyecto", "HitoHeading2"))
    local_refs = [
        "PreventiTI_Hito1.pdf, documento aportado por el usuario (15/09/2026).",
        "docs/planificacion/ALCANCE_Y_HITOS_ACTUALES.md, revisión 22/10/2026.",
        "docs/planificacion/Backlog_Jira_PreventiTI.csv, registro PVT-001 a PVT-038.",
        "docs/arquitectura/MEMORIA_TECNICA_CONSOLIDADA.md, corte 20/10/2026.",
        "docs/guias/ACCESOS_Y_ROLES.md, actualización 21/10/2026.",
        "app/Services/BackupService.php; composer.json / composer.lock; package.json / package-lock.json; suite tests/ y ejecución local del 27/10/2026.",
        "Declaraciones del equipo incorporadas para esta actualización: Hito 1 entregado; áreas H2 indicadas entregadas; aproximadamente 80 horas por integrante (27/10/2026).",
    ]
    for i, ref in enumerate(local_refs, 1):
        story.append(para(f"[P{i}] {escape(ref)}", "Small"))
    story.append(para("Fuentes externas", "HitoHeading2"))
    urls = [
        ("GLPI · funciones", "https://www.glpi-project.org/en/features/"),
        ("Snipe-IT · producto", "https://snipeitapp.com/product"),
        ("Freshservice · precios y planes", "https://www.freshworks.com/freshservice/pricing/"),
        ("IMPO · Ley 18.331, artículo 10", "https://www.impo.com.uy/bases/leyes/18331-2008/10"),
        ("IMPO · Ley 18.331, artículo 12", "https://www.impo.com.uy/bases/leyes/18331-2008/12"),
        ("IMPO · Decreto 414/009", "https://www.impo.com.uy/bases/decretos/414-2009"),
        ("ISO · ISO/IEC 27001:2022", "https://www.iso.org/standard/27001"),
        ("ISO · ISO/IEC 27005:2022", "https://www.iso.org/standard/80585.html"),
        ("ISO · ISO/IEC 25010:2023", "https://www.iso.org/standard/78176.html"),
        ("Ugreen DXP4800 · precio listado Gamer.com.uy", "https://gamer.com.uy/nas-ugreen-dxp4800-4-bahias.html"),
        ("WD Red Plus NAS 8 TB · listado NNet", "https://www.nnet.com.uy/catalogo/mundo-gamer/componentes/almacenamiento/hdd-disco-duro-mecanico/disco-wd-red-plus-nas-8tb-5640rpm-dh964wdc35/"),
        ("Switch PoE administrable 48 puertos · Distriseg", "https://www.distriseg.uy/shop/wi-pms552f-switch-poe-administrable-l2-48-puertos-gigabit-4-sfp-10g-780w-136883"),
        ("Switch 48 puertos · catálogo PricePC", "https://www.preciospc.com.uy/marca/linksys"),
        ("UPS 1500 VA / 900 W · NNet", "https://www.nnet.com.uy/es/ups-nrg-1500va-900w-con-pantalla-lcd/"),
        ("Backblaze B2 · precios de almacenamiento", "https://www.backblaze.com/cloud-storage/pricing"),
        ("Referencias de tarifa freelance en tecnología en Uruguay (orientativa)", "https://equipomedios.com/blog/freelance-en-tecnologia-cuanto-cobrar-y-como-empezar-desde-uruguay/"),
        ("Laravel · requisitos y despliegue", "https://laravel.com/framework/docs/deployment"),
        ("Proxmox VE · descargas", "https://www.proxmox.com/en/downloads"),
        ("Ubuntu · ciclo de versiones", "https://ubuntu.com/project/docs/release-team/list-of-releases/"),
        ("pfSense · versiones", "https://docs.netgate.com/pfsense/en/latest/releases/versions.html"),
        ("MariaDB · versiones publicadas", "https://mariadb.org/mariadb/all-releases/"),
        ("ISO · ISO/IEC 27002:2022", "https://www.iso.org/standard/75652.html"),
        ("Ubuntu Packages · Nginx para Ubuntu 24.04", "https://packages.ubuntu.com/en/noble/nginx"),
        ("Ubuntu Packages · PHP-FPM 8.3 para Ubuntu 24.04", "https://packages.ubuntu.com/noble/any/php8.3-fpm"),
    ]
    for i, (title, url) in enumerate(urls, 1):
        link = f'<link href="{escape(url)}" color="#176B87">{escape(url)}</link>'
        story.append(para(f"[{i}] <b>{escape(title)}</b> · {link}", "Small"))
    story.append(Spacer(1, 5))
    story.append(para("Consulta: 27/10/2026 para normativa, estándares, versiones de referencia y publicaciones comerciales. Los precios cambian; requieren cotización antes de comprar. Los enlaces a organismos normativos informan el estándar y su alcance, no certifican a PreventiTI.", "Small"))
    return story


def main():
    OUTPUT.parent.mkdir(parents=True, exist_ok=True)
    doc = PreventiDoc(str(OUTPUT))
    doc.multiBuild(build_story(), maxPasses=4)
    print(f"PDF generado: {OUTPUT}")


if __name__ == "__main__":
    main()
