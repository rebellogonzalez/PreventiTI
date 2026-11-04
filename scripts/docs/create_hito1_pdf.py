from pathlib import Path

from PIL import Image as PILImage
from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER, TA_LEFT
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle
from reportlab.lib.utils import ImageReader
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.pdfgen import canvas
from reportlab.platypus import Paragraph, Table, TableStyle


ROOT = Path(__file__).resolve().parents[2]
OUT = ROOT / "output/pdf/PreventiTI_Hito_1_Conectividad_y_Seguridad.pdf"
DIAGRAM = Path("/Users/ramirorebello/Downloads/Diagrama sin título.drawio.png")

PAGE_W, PAGE_H = A4
MARGIN = 40

NAVY = colors.HexColor("#10233F")
BLUE = colors.HexColor("#2457D6")
TEAL = colors.HexColor("#0F766E")
INK = colors.HexColor("#182235")
MUTED = colors.HexColor("#58657A")
LIGHT = colors.HexColor("#F3F6FA")
LINE = colors.HexColor("#D7DFEA")
PALE_BLUE = colors.HexColor("#EAF0FF")
PALE_TEAL = colors.HexColor("#E7F5F2")
PALE_AMBER = colors.HexColor("#FFF4DB")
PALE_RED = colors.HexColor("#FDECEC")
GREEN = colors.HexColor("#137333")
AMBER = colors.HexColor("#A85B00")
RED = colors.HexColor("#B42318")
WHITE = colors.white


def register_fonts():
    font_dir = Path("/System/Library/Fonts/Supplemental")
    pdfmetrics.registerFont(TTFont("Arial", str(font_dir / "Arial.ttf")))
    pdfmetrics.registerFont(TTFont("Arial-Bold", str(font_dir / "Arial Bold.ttf")))
    pdfmetrics.registerFont(TTFont("Arial-Italic", str(font_dir / "Arial Italic.ttf")))


register_fonts()

STYLE_BODY = ParagraphStyle(
    "body", fontName="Arial", fontSize=8.5, leading=11.2, textColor=INK,
)
STYLE_BODY_SMALL = ParagraphStyle(
    "body-small", fontName="Arial", fontSize=7.4, leading=9.3, textColor=INK,
)
STYLE_TABLE = ParagraphStyle(
    "table", fontName="Arial", fontSize=7.1, leading=8.7, textColor=INK,
)
STYLE_TABLE_CENTER = ParagraphStyle(
    "table-center", parent=STYLE_TABLE, alignment=TA_CENTER,
)
STYLE_WHITE = ParagraphStyle(
    "white", fontName="Arial", fontSize=8.4, leading=10.5, textColor=WHITE,
)
STYLE_WHITE_SMALL = ParagraphStyle(
    "white-small", fontName="Arial", fontSize=7.2, leading=8.8, textColor=WHITE,
)


def p(text, style=STYLE_TABLE):
    return Paragraph(text, style)


def draw_wrapped(c, text, x, top_y, width, style=STYLE_BODY):
    para = Paragraph(text, style)
    _, h = para.wrap(width, PAGE_H)
    para.drawOn(c, x, top_y - h)
    return top_y - h


def draw_footer(c, page_num, label):
    c.setStrokeColor(LINE)
    c.setLineWidth(0.6)
    c.line(MARGIN, 29, PAGE_W - MARGIN, 29)
    c.setFont("Arial", 6.8)
    c.setFillColor(MUTED)
    c.drawString(MARGIN, 16, f"PreventiTI  |  Hito 1: Conectividad y Seguridad  |  {label}")
    c.drawRightString(PAGE_W - MARGIN, 16, f"Página {page_num} de 4")


def draw_page_title(c, number, title, subtitle):
    c.setFillColor(NAVY)
    c.rect(0, PAGE_H - 92, PAGE_W, 92, fill=1, stroke=0)
    c.setFillColor(colors.HexColor("#7DD3FC"))
    c.setFont("Arial-Bold", 8)
    c.drawString(MARGIN, PAGE_H - 28, f"HITO 1  /  {number}")
    c.setFillColor(WHITE)
    c.setFont("Arial-Bold", 20)
    c.drawString(MARGIN, PAGE_H - 53, title)
    c.setFont("Arial", 8.5)
    c.setFillColor(colors.HexColor("#D8E4F4"))
    c.drawString(MARGIN, PAGE_H - 71, subtitle)
    c.setFillColor(colors.HexColor("#37C5B4"))
    c.rect(MARGIN, PAGE_H - 92, 92, 4, fill=1, stroke=0)


def rounded_box(c, x, y, w, h, fill, stroke=LINE, radius=8):
    c.setFillColor(fill)
    c.setStrokeColor(stroke)
    c.setLineWidth(0.7)
    c.roundRect(x, y, w, h, radius, fill=1, stroke=1)


def draw_section_label(c, text, x, y, color=BLUE):
    c.setFillColor(color)
    c.roundRect(x, y - 12, 18, 18, 4, fill=1, stroke=0)
    c.setFillColor(WHITE)
    c.setFont("Arial-Bold", 8)
    c.drawCentredString(x + 9, y - 6, text.split()[0])
    c.setFillColor(INK)
    c.setFont("Arial-Bold", 10.5)
    c.drawString(x + 27, y - 7, " ".join(text.split()[1:]))


def draw_table(c, rows, x, top_y, widths, styles=None, row_heights=None, repeat_rows=1):
    table = Table(rows, colWidths=widths, rowHeights=row_heights, repeatRows=repeat_rows)
    base = [
        ("BACKGROUND", (0, 0), (-1, 0), NAVY),
        ("TEXTCOLOR", (0, 0), (-1, 0), WHITE),
        ("FONTNAME", (0, 0), (-1, 0), "Arial-Bold"),
        ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
        ("GRID", (0, 0), (-1, -1), 0.45, LINE),
        ("LEFTPADDING", (0, 0), (-1, -1), 5),
        ("RIGHTPADDING", (0, 0), (-1, -1), 5),
        ("TOPPADDING", (0, 0), (-1, -1), 4),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 4),
    ]
    for i in range(1, len(rows)):
        base.append(("BACKGROUND", (0, i), (-1, i), WHITE if i % 2 else LIGHT))
    if styles:
        base.extend(styles)
    table.setStyle(TableStyle(base))
    _, h = table.wrap(sum(widths), PAGE_H)
    table.drawOn(c, x, top_y - h)
    return top_y - h


def page_1(c):
    c.setFillColor(NAVY)
    c.rect(0, PAGE_H - 196, PAGE_W, 196, fill=1, stroke=0)
    c.setFillColor(colors.HexColor("#6EE7D8"))
    c.roundRect(MARGIN, PAGE_H - 52, 122, 24, 12, fill=1, stroke=0)
    c.setFillColor(NAVY)
    c.setFont("Arial-Bold", 8)
    c.drawCentredString(MARGIN + 61, PAGE_H - 44, "ENTREGABLE ACADÉMICO")
    c.setFillColor(WHITE)
    c.setFont("Arial-Bold", 26)
    c.drawString(MARGIN, PAGE_H - 93, "Hito 1")
    c.setFont("Arial-Bold", 19)
    c.drawString(MARGIN, PAGE_H - 121, "Diseño y análisis de arquitectura segura")
    c.setFont("Arial", 10)
    c.setFillColor(colors.HexColor("#D8E4F4"))
    c.drawString(MARGIN, PAGE_H - 147, "Conectividad y Seguridad  |  Proyecto PreventiTI")
    c.setFont("Arial", 8)
    c.drawString(MARGIN, PAGE_H - 169, "Versión 1.0  |  1 de octubre de 2026  |  Equipo Carisma")

    rounded_box(c, MARGIN, 578, PAGE_W - 2 * MARGIN, 50, PALE_TEAL, colors.HexColor("#A9DAD1"))
    c.setFillColor(TEAL)
    c.setFont("Arial-Bold", 8)
    c.drawString(MARGIN + 13, 609, "ESTADO DEL DISEÑO")
    c.setFillColor(INK)
    c.setFont("Arial-Bold", 11)
    c.drawString(MARGIN + 13, 590, "Arquitectura de red aprobada por el equipo del proyecto")
    c.setFont("Arial", 7.5)
    c.setFillColor(MUTED)
    c.drawRightString(PAGE_W - MARGIN - 13, 591, "Se preservan VLAN, IP y política inter-VLAN vigentes")

    draw_section_label(c, "01 Resumen ejecutivo", MARGIN, 554)
    y = draw_wrapped(
        c,
        "Este documento formaliza el diseño seguro de la red de PreventiTI para el Hito 1. La solución segmenta clientes y servicios en dos VLAN, concentra el enrutamiento y el filtrado en pfSense CE 2.7, protege la administración remota mediante WireGuard y publica la aplicación únicamente por HTTPS. El análisis mantiene el alcance ya aprobado y registra riesgos, controles, tecnologías y planificación sin cambiar el direccionamiento ni la implementación.",
        MARGIN,
        523,
        PAGE_W - 2 * MARGIN,
    )

    gap = 14
    col_w = (PAGE_W - 2 * MARGIN - gap) / 2
    top = y - 18
    rounded_box(c, MARGIN, top - 176, col_w, 176, LIGHT)
    rounded_box(c, MARGIN + col_w + gap, top - 176, col_w, 176, LIGHT)

    c.setFillColor(BLUE)
    c.setFont("Arial-Bold", 10)
    c.drawString(MARGIN + 14, top - 22, "Objetivos del hito")
    objective_text = (
        "<b>1.</b> Definir la topología física y lógica.<br/>"
        "<b>2.</b> Identificar activos, flujos, riesgos y vulnerabilidades.<br/>"
        "<b>3.</b> Justificar tecnologías, protocolos y dispositivos.<br/>"
        "<b>4.</b> Establecer un plan verificable de implementación y evidencia."
    )
    draw_wrapped(c, objective_text, MARGIN + 14, top - 38, col_w - 28, STYLE_BODY)

    c.setFillColor(TEAL)
    c.setFont("Arial-Bold", 10)
    c.drawString(MARGIN + col_w + gap + 14, top - 22, "Criterios de seguridad")
    criteria_text = (
        "<b>Segmentación:</b> separación entre operación y servidores.<br/>"
        "<b>Mínimo privilegio:</b> solo HTTPS desde clientes.<br/>"
        "<b>Canal seguro:</b> VPN para administración técnica.<br/>"
        "<b>Trazabilidad:</b> reglas, logs y pruebas asociados a cada control."
    )
    draw_wrapped(c, criteria_text, MARGIN + col_w + gap + 14, top - 38, col_w - 28, STYLE_BODY)

    y2 = top - 198
    draw_section_label(c, "02 Alcance y decisiones", MARGIN, y2)
    rows = [
        [p("<b>Elemento</b>", STYLE_WHITE_SMALL), p("<b>Decisión del diseño</b>", STYLE_WHITE_SMALL), p("<b>Resultado</b>", STYLE_WHITE_SMALL)],
        [p("Segmentación"), p("VLAN 10 para clientes/POS y VLAN 20 para servidores y gestión"), p("Reduce exposición directa de servicios")],
        [p("Perímetro"), p("pfSense CE 2.7 como firewall y enrutador inter-VLAN"), p("Política centralizada y auditable")],
        [p("Administración"), p("WireGuard 10.10.0.0/24; peer técnico 10.10.0.2/32"), p("SSH disponible solo por VPN autorizada")],
        [p("Aplicación"), p("Nginx + PHP-FPM + Laravel por HTTPS TCP/443"), p("Acceso cifrado a PreventiTI")],
    ]
    y3 = draw_table(c, rows, MARGIN, y2 - 27, [92, 259, 164], row_heights=[22, 30, 30, 30, 30])

    rounded_box(c, MARGIN, 47, PAGE_W - 2 * MARGIN, 47, PALE_AMBER, colors.HexColor("#E7C77F"))
    c.setFillColor(AMBER)
    c.setFont("Arial-Bold", 7.7)
    c.drawString(MARGIN + 12, 78, "LÍMITES DOCUMENTADOS")
    c.setFillColor(INK)
    draw_wrapped(
        c,
        "La IP WAN y el DNS entregado por DHCP están por relevar. Las IP 192.168.20.30-.32 y .40-.43 son propuestas de gestión y deben cotejarse con el laboratorio aprobado antes de aplicarse.",
        MARGIN + 12,
        70,
        PAGE_W - 2 * MARGIN - 24,
        STYLE_BODY_SMALL,
    )
    draw_footer(c, 1, "Síntesis")
    c.showPage()


def page_2(c):
    draw_page_title(c, "02", "Arquitectura de red vigente", "Topología completa, direccionamiento y flujos autorizados")

    img = PILImage.open(DIAGRAM)
    iw, ih = img.size
    max_w = PAGE_W - 2 * MARGIN
    max_h = 466
    scale = min(max_w / iw, max_h / ih)
    dw, dh = iw * scale, ih * scale
    x = (PAGE_W - dw) / 2
    y = PAGE_H - 112 - dh
    c.setFillColor(colors.HexColor("#101418"))
    c.roundRect(x - 4, y - 4, dw + 8, dh + 8, 5, fill=1, stroke=0)
    c.drawImage(ImageReader(img), x, y, width=dw, height=dh, preserveAspectRatio=True, mask="auto")
    c.setFillColor(MUTED)
    c.setFont("Arial-Italic", 6.9)
    c.drawCentredString(PAGE_W / 2, y - 15, "Figura 1. Arquitectura física y lógica vigente de PreventiTI. Fuente: diagrama Draw.io suministrado.")

    table_top = y - 34
    rows = [
        [p("<b>Segmento / equipo</b>", STYLE_WHITE_SMALL), p("<b>Dirección</b>", STYLE_WHITE_SMALL), p("<b>Función</b>", STYLE_WHITE_SMALL)],
        [p("VLAN 10 - Clientes/POS"), p("192.168.10.0/24"), p("DHCP .50-.200; GW 192.168.10.1")],
        [p("VLAN 20 - Servidores"), p("192.168.20.0/24"), p("GW 192.168.20.1")],
        [p("Ubuntu / Windows"), p("192.168.20.10 / .20"), p("Web + DB local / AD DS")],
        [p("VPN WireGuard"), p("10.10.0.1/24; peer .2/32"), p("UDP/51820; administración")],
    ]
    draw_table(c, rows, MARGIN, table_top, [156, 156, 203], row_heights=[20, 26, 26, 26, 26])

    rounded_box(c, MARGIN, 48, PAGE_W - 2 * MARGIN, 54, PALE_BLUE, colors.HexColor("#B5C7F3"))
    c.setFillColor(BLUE)
    c.setFont("Arial-Bold", 7.6)
    c.drawString(MARGIN + 12, 83, "POLÍTICA DE TRÁFICO")
    c.setFillColor(INK)
    draw_wrapped(
        c,
        "Regla 1: PASS TCP desde 192.168.10.0/24 hacia 192.168.20.10:443. Regla 2: BLOCK TCP desde VLAN 10 hacia VLAN 20 en 22 y 3306. Los controles de WAN y WireGuard sostienen la VPN y no agregan reglas al conjunto inter-VLAN aprobado.",
        MARGIN + 12,
        75,
        PAGE_W - 2 * MARGIN - 24,
        STYLE_BODY_SMALL,
    )
    draw_footer(c, 2, "Diseño de red")
    c.showPage()


def page_3(c):
    draw_page_title(c, "03", "Riesgos, vulnerabilidades y controles", "Evaluación cualitativa con tratamiento proporcional al alcance aprobado")

    c.setFillColor(MUTED)
    c.setFont("Arial", 7.8)
    c.drawString(MARGIN, PAGE_H - 112, "Escala: probabilidad e impacto Alto / Medio / Bajo. Prioridad = exposición residual tras los controles vigentes.")

    risk_rows = [
        [p("<b>Riesgo principal</b>", STYLE_WHITE_SMALL), p("<b>P / I</b>", STYLE_WHITE_SMALL), p("<b>Prioridad</b>", STYLE_WHITE_SMALL), p("<b>Control vigente y tratamiento mínimo</b>", STYLE_WHITE_SMALL)],
        [p("Movimiento lateral desde clientes, POS o IoT"), p("M / A", STYLE_TABLE_CENTER), p("Alta", STYLE_TABLE_CENTER), p("Dos VLAN y bloqueo 22/3306. Mantener inventario, reservas y aislamiento de cliente en AP cuando esté disponible.")],
        [p("Acceso administrativo no autorizado"), p("M / A", STYLE_TABLE_CENTER), p("Alta", STYLE_TABLE_CENTER), p("WireGuard con peer /32. Sustituir claves de ejemplo, custodiar la privada y revocar accesos al cambiar personal.")],
        [p("Exposición o degradación del servicio web"), p("M / A", STYLE_TABLE_CENTER), p("Alta", STYLE_TABLE_CENTER), p("HTTPS 443 y Nginx. Mantener TLS 1.2/1.3, renovar certificado y limitar módulos y servicios del host.")],
        [p("Falla de pfSense, switch principal o servidor"), p("M / A", STYLE_TABLE_CENTER), p("Alta", STYLE_TABLE_CENTER), p("Respaldar configuración, documentar recuperación y usar UPS. Registrar RTO/RPO antes de producción.")],
        [p("Direccionamiento incompleto o duplicado"), p("M / M", STYLE_TABLE_CENTER), p("Media", STYLE_TABLE_CENTER), p("Conservar IP aprobadas. Relevar WAN, DNS, reservas y validar .30-.43 antes de asignar gestión.")],
        [p("Falta de evidencia y monitoreo operativo"), p("M / M", STYLE_TABLE_CENTER), p("Media", STYLE_TABLE_CENTER), p("Conservar exportación pfSense, handshake WireGuard, logs y resultados Nmap reales con fecha, origen y responsable.")],
    ]
    risk_styles = [
        ("TEXTCOLOR", (2, 1), (2, 4), RED),
        ("FONTNAME", (2, 1), (2, -1), "Arial-Bold"),
        ("TEXTCOLOR", (2, 5), (2, -1), AMBER),
    ]
    y = draw_table(c, risk_rows, MARGIN, PAGE_H - 126, [113, 42, 54, 306], styles=risk_styles, row_heights=[24, 57, 57, 57, 57, 57, 57])

    draw_section_label(c, "04 Tecnologías seleccionadas", MARGIN, y - 22, TEAL)
    tech_rows = [
        [p("<b>Tecnología / dispositivo</b>", STYLE_WHITE_SMALL), p("<b>Uso en la arquitectura</b>", STYLE_WHITE_SMALL), p("<b>Aporte de seguridad</b>", STYLE_WHITE_SMALL)],
        [p("pfSense CE 2.7"), p("Firewall, routing y terminación VPN"), p("Filtrado central y registro")],
        [p("IEEE 802.1Q + switches L2"), p("Troncales VLAN 10 y 20"), p("Separación de dominios de difusión")],
        [p("WireGuard UDP/51820"), p("Acceso remoto del técnico líder"), p("Cifrado y peer autorizado")],
        [p("HTTPS TLS + Nginx"), p("PreventiTI en TCP/443"), p("Confidencialidad e integridad")],
        [p("MariaDB 10.11+ local"), p("Persistencia en Ubuntu; TCP/3306 local"), p("Sin acceso directo desde VLAN 10")],
        [p("Wi-Fi 6 + AD DS"), p("Acceso inalámbrico y directorio"), p("Gestión central y capacidad operativa")],
    ]
    draw_table(c, tech_rows, MARGIN, y - 50, [150, 182, 183], row_heights=[22, 29, 29, 29, 29, 29, 29])

    rounded_box(c, MARGIN, 47, PAGE_W - 2 * MARGIN, 42, PALE_TEAL, colors.HexColor("#A9DAD1"))
    c.setFillColor(TEAL)
    c.setFont("Arial-Bold", 7.5)
    c.drawString(MARGIN + 12, 73, "RIESGO RESIDUAL ACEPTADO")
    c.setFillColor(INK)
    c.setFont("Arial", 7.4)
    c.drawString(MARGIN + 12, 58, "La arquitectura mantiene dos VLAN por alcance académico; los controles operativos y la evidencia compensan esa simplificación.")
    draw_footer(c, 3, "Análisis de seguridad")
    c.showPage()


def page_4(c):
    draw_page_title(c, "04", "Plan general, cronograma y aceptación", "Secuencia de trabajo del Hito 1 y evidencia mínima de cierre")

    draw_section_label(c, "05 Cronograma del hito", MARGIN, PAGE_H - 117)
    schedule_rows = [
        [p("<b>Semana</b>", STYLE_WHITE_SMALL), p("<b>Actividad</b>", STYLE_WHITE_SMALL), p("<b>Resultado / evidencia</b>", STYLE_WHITE_SMALL), p("<b>Responsable</b>", STYLE_WHITE_SMALL)],
        [p("1", STYLE_TABLE_CENTER), p("Relevamiento de alcance, activos, dependencias y riesgos"), p("Inventario base, supuestos y matriz inicial"), p("Redes + equipo")],
        [p("2", STYLE_TABLE_CENTER), p("Diseño de VLAN, direccionamiento, topología y flujos"), p("Diagrama, tabla IP y política de tráfico"), p("Redes")],
        [p("3", STYLE_TABLE_CENTER), p("Selección de protocolos, dispositivos y controles"), p("Especificación pfSense, WireGuard, TLS y pruebas"), p("Redes + seguridad")],
        [p("4", STYLE_TABLE_CENTER), p("Revisión, trazabilidad, ajustes documentales y aprobación"), p("Documento Hito 1 y registro de revisión"), p("Equipo Carisma")],
    ]
    y = draw_table(c, schedule_rows, MARGIN, PAGE_H - 147, [45, 204, 196, 70], row_heights=[24, 43, 43, 43, 43])

    draw_section_label(c, "06 Criterios de aceptación", MARGIN, y - 22, BLUE)
    acceptance_rows = [
        [p("<b>Verificación</b>", STYLE_WHITE_SMALL), p("<b>Criterio esperado</b>", STYLE_WHITE_SMALL), p("<b>Evidencia a conservar</b>", STYLE_WHITE_SMALL)],
        [p("Segmentación"), p("VLAN 10 y VLAN 20 activas; gateways .10.1 y .20.1"), p("Exportación pfSense y configuración de puertos")],
        [p("Acceso web"), p("192.168.20.10:443 accesible desde VLAN 10"), p("Nmap/curl, certificado y log Nginx")],
        [p("Bloqueos"), p("TCP/22 y TCP/3306 filtrados desde VLAN 10"), p("Nmap real y logs del firewall")],
        [p("VPN"), p("Handshake del peer 10.10.0.2/32 y acceso autorizado"), p("Estado WireGuard y registro de sesión")],
        [p("Datos pendientes"), p("WAN, DNS, reservas e IP de gestión cotejados"), p("Acta de relevamiento y plan IP actualizado")],
    ]
    y2 = draw_table(c, acceptance_rows, MARGIN, y - 50, [120, 212, 183], row_heights=[22, 35, 35, 35, 35, 35])

    draw_section_label(c, "07 Conclusión", MARGIN, y2 - 21, TEAL)
    rounded_box(c, MARGIN, y2 - 116, PAGE_W - 2 * MARGIN, 83, LIGHT)
    draw_wrapped(
        c,
        "El diseño es coherente con el alcance aprobado: separa operación y servicios, expone únicamente HTTPS a los clientes y reserva la administración para una VPN autorizada. La cobertura del Hito 1 queda completa al vincular topología, direccionamiento, riesgos, tecnologías y cronograma. Las acciones pendientes son documentales y operativas: relevar los valores reales, custodiar secretos y conservar evidencias; no requieren rediseñar la red.",
        MARGIN + 13,
        y2 - 48,
        PAGE_W - 2 * MARGIN - 26,
        STYLE_BODY,
    )

    rounded_box(c, MARGIN, 48, PAGE_W - 2 * MARGIN, 72, PALE_BLUE, colors.HexColor("#B5C7F3"))
    c.setFillColor(BLUE)
    c.setFont("Arial-Bold", 7.4)
    c.drawString(MARGIN + 12, 103, "TRAZABILIDAD DOCUMENTAL")
    c.setFillColor(INK)
    draw_wrapped(
        c,
        "Fuentes internas: docs/arquitectura/TOPOLOGIA_RED_PREVENTITI.md; infrastructure/configs/pfsense_rules.md; infrastructure/configs/wireguard_wg0.conf; infrastructure/configs/nginx_preventiti.conf; docs/planificacion/ALCANCE_Y_HITOS_ACTUALES.md. Figura: archivo Draw.io aportado por el usuario.",
        MARGIN + 12,
        94,
        PAGE_W - 2 * MARGIN - 24,
        STYLE_BODY_SMALL,
    )
    draw_footer(c, 4, "Planificación y cierre")
    c.showPage()


def main():
    OUT.parent.mkdir(parents=True, exist_ok=True)
    c = canvas.Canvas(str(OUT), pagesize=A4, pageCompression=1)
    c.setTitle("PreventiTI - Hito 1 de Conectividad y Seguridad")
    c.setAuthor("Equipo Carisma")
    c.setSubject("Diseño y análisis de arquitectura segura")
    page_1(c)
    page_2(c)
    page_3(c)
    page_4(c)
    c.save()
    print(OUT)


if __name__ == "__main__":
    main()
