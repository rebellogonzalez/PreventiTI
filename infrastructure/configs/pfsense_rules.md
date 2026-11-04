# PreventiTI — Especificación de Reglas de Firewall pfSense CE 2.7

**Autor:** Ignacio "Nacho" de la Rosa  
**Área:** Conectividad, Redes y Ciberseguridad Perimetral  
**Estado:** arquitectura e implementación de red aprobadas, según confirmación del equipo del proyecto.  
**Cumplimiento Estricto:** EXACTAMENTE 2 reglas de filtrado entre VLAN 10 y VLAN 20, sin reglas inter-VLAN arbitrarias adicionales.

---

## 1. Topología de Interfaces en pfSense
- **WAN:** Conexión hacia Gateway externo / Router.
- **LAN_VLAN10 (Clientes & Salón):** `192.168.10.1/24` (DHCP pool `.10.50 - .10.200`; gateway entregado: `192.168.10.1`).
- **LAN_VLAN20 (Servidores):** `192.168.20.1/24` (Estáticas para Ubuntu `.10` y Windows `.20`).
- **WG_VPN (WireGuard):** `10.10.0.1/24`, escucha UDP `51820` (Acceso exclusivo Técnico Líder).

La IP de DNS entregada por DHCP no está declarada en los archivos disponibles. Debe conservarse el valor de la implementación aprobada y documentarse al exportar la configuración de pfSense; no se asigna una IP nueva en esta especificación.

---

## 2. Las 2 Reglas de Filtrado Inter-VLAN Obligatorias

El alcance de “exactamente 2 reglas” corresponde al tráfico iniciado en VLAN 10 hacia VLAN 20. No incluye los controles operativos de la WAN ni de la interfaz WireGuard necesarios para establecer el túnel administrativo aprobado.

### REGLA 1: Tráfico Web Seguro Permitido (PASS)
- **Interfaz:** `VLAN 10`
- **Acción:** `PASS`
- **Protocolo:** `TCP`
- **Origen:** `VLAN 10 net (192.168.10.0/24)`
- **Puerto Origen:** `*`
- **Destino:** `192.168.20.10` (Ubuntu Server)
- **Puerto Destino:** `443 (HTTPS)`
- **Descripción:** `[REGLA-01-PASS] Autorizar trafico web HTTPS desde clientes hacia servidor PreventiTI`
- **Justificación:** Permite a los empleados en línea de cajas y asesores de salón operar la SPA web para consultar activos y registrar incidencias.

### REGLA 2: Bloqueo de Puertos Administrativos y Bases de Datos (BLOCK)
- **Interfaz:** `VLAN 10`
- **Acción:** `BLOCK (DROP)`
- **Protocolo:** `TCP`
- **Origen:** `VLAN 10 net (192.168.10.0/24)`
- **Puerto Origen:** `*`
- **Destino:** `VLAN 20 net (192.168.20.0/24)`
- **Puerto Destino (Alias `ADMIN_PORTS`):** `22 (SSH), 3306 (MySQL)`
- **Descripción:** `[REGLA-02-BLOCK] Denegar acceso a puertos de gestion SSH y base de datos desde salon`
- **Justificación (Caso CP-04):** Impide que cualquier host comprometido en cajas o salón pueda intentar ataques de fuerza bruta por SSH o acceso directo a MariaDB.

---

## 3. Acceso administrativo por WireGuard

- **Transporte del túnel:** UDP `51820` hacia la interfaz WAN de pfSense.
- **Peer autorizado:** Técnico Líder con `10.10.0.2/32`.
- **Destino administrativo:** acceso por el túnel a los servicios de gestión autorizados, incluido SSH en el servidor Ubuntu cuando corresponda.
- **Alcance:** los permisos operativos de WAN/WireGuard sostienen la VPN; no agregan una tercera regla al conjunto inter-VLAN descrito en la sección 2.

Esta sección documenta el comportamiento de la implementación aprobada. Las claves y el detalle exportable permanecen fuera de este archivo y no se modifican.

---

## 4. Verificación Nmap (Caso de Prueba CP-04)
Comando ejecutado desde un host en VLAN 10:
```bash
nmap -p 22,443,3306 192.168.20.10
```
Resultado utilizado como criterio de aceptación:
- `22/tcp FILTERED`
- `443/tcp OPEN`
- `3306/tcp FILTERED`
