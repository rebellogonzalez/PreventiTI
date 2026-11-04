---
marp: true
theme: default
class: lead
paginate: true
backgroundColor: #f8f9fa
---

# 🛡️ Gestión de monitoreo y prevención de fallas

### Equipo Carisma

**Integrantes:**
- 👤 Ignacio de la Rosa
- 👤 Daniel Sanz
- 👤 Ramiro Rebello

---

# 🚀 El Proyecto y el Equipo

**PreventiTI** es un sistema web centralizado para administrar el ciclo de vida de fallas y automatizar el mantenimiento preventivo del equipamiento tecnológico.

### 👥 Distribución del Trabajo

- **Ignacio de la Rosa** ➔ Documentación y base de datos
- **Daniel Sanz** ➔ Redes y estructura
- **Ramiro Rebello** ➔ Implementación y planificación

---

# ⚠️ El Problema

La gestión tecnológica tradicional es **reactiva** y genera ineficiencias:

- 🛑 **Operación Reactiva:** El soporte actúa solo cuando el equipo ya quedó inoperativo.
- 📉 **Inventarios Aislados:** Planillas estáticas sin control real de ubicaciones.
- 🖨️ **Falta de Insumos:** Paradas por consumibles agotados (papel, tóner, baterías).
- ❌ **Consecuencia Principal:** Tiempos muertos y caída en la calidad de atención.

---

# 💡 Nuestra Solución

Transformamos el caos reactivo en un modelo **proactivo** y **auditable**.

### 🔄 Flujo Operativo:
1. ⚠️ **Problema original** o advertencia de consumible.
2. 💻 Ingresa al **Sistema PreventiTI**.
3. 🔎 Se acciona mediante:
   - **Monitoreo Constante**
   - **Reportes y notificaciones**
   - **Prevención Activa**
4. ✅ Resultado: **Continuidad Operativa Garantizada**.

---

# 🖥️ Recorrido por el Sistema

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; text-align: center; margin-top: 20px;">
  <div>
    <img src="/Users/ramirorebello/.gemini/antigravity/brain/82a09377-2663-4ee9-ae49-dfa4bd51ff3f/.user_uploaded/media_1788902758595.png" style="width: 100%; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
    <p style="font-size: 0.6em; margin-top: 5px;">📊 <b>Tablero Principal:</b> Vista global y alertas</p>
  </div>
  <div>
    <img src="/Users/ramirorebello/.gemini/antigravity/brain/82a09377-2663-4ee9-ae49-dfa4bd51ff3f/.user_uploaded/media_1788902773920.png" style="width: 100%; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
    <p style="font-size: 0.6em; margin-top: 5px;">📋 <b>Reportes:</b> Registro y notificaciones persistentes</p>
  </div>
  <div>
    <img src="/Users/ramirorebello/.gemini/antigravity/brain/82a09377-2663-4ee9-ae49-dfa4bd51ff3f/.user_uploaded/media_1788902791335.png" style="width: 100%; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
    <p style="font-size: 0.6em; margin-top: 5px;">🗺️ <b>Mapa de Dispositivos:</b> Ubicación visual</p>
  </div>
  <div>
    <img src="/Users/ramirorebello/.gemini/antigravity/brain/82a09377-2663-4ee9-ae49-dfa4bd51ff3f/.user_uploaded/media_1788902809869.png" style="width: 100%; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
    <p style="font-size: 0.6em; margin-top: 5px;">📈 <b>Estadísticas:</b> Control y proyecciones</p>
  </div>
</div>

---

# 👥 Roles y Jerarquías

Organización y control de acceso basado en perfiles (RBAC):

<br>

- 👑 **Administrador** ➔ Métricas, Auditoría y Control Total del Sistema.
- 🔧 **Técnico** ➔ Diagnóstico, Resolución de fallas y Mantenimiento.
- 👤 **Empleado** ➔ Reporte Ágil de Fallas en su área de trabajo.

---

# ⚙️ Arquitectura y Funcionamiento

Funcionamiento segmentado sobre la arquitectura de red aprobada.

<br>

- 👥 **Usuarios VLAN 10** ➔ HTTPS TCP/443 ➔ 💻 **Ubuntu `192.168.20.10`**
- 💻 **Servidor Web** ➔ conexión local ➔ 🗄️ **MariaDB en el mismo servidor**
- 🛡️ **Líder Técnico `10.10.0.2/32`** ➔ WireGuard UDP/51820 ➔ 🔐 **pfSense `10.10.0.1/24`**
- 🔒 **Política inter-VLAN:** PASS HTTPS a `.20.10`; BLOCK SSH/MySQL desde VLAN 10.

---

# 🎯 Resultado y Cierre

- **El Problema Superado:** Erradicamos la operación puramente reactiva.
- **El Aporte:** Visibilidad en tiempo real, trazabilidad histórica y automatización de procesos.
- **El Alcance:** Registro de reportes, notificaciones persistentes, alertas de insumos y prevención de fallas antes de que ocurran.

<br>

### *"De la reacción a la prevención: asegurando la continuidad del servicio."*
