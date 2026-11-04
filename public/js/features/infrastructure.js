import { escapeHTML as esc } from '../lib/html.js';

export function formatBytes(value) {
  if (!Number.isFinite(Number(value)) || value == null) return 'No disponible';
  const units = ['B', 'KB', 'MB', 'GB', 'TB'];
  let amount = Number(value), index = 0;
  while (amount >= 1024 && index < units.length - 1) { amount /= 1024; index++; }
  return `${amount.toLocaleString('es-UY', { maximumFractionDigits: 1 })} ${units[index]}`;
}
const dateLabel = value => value ? new Date(value).toLocaleString('es-UY', { timeZone: 'America/Montevideo' }) : 'Sin fecha';

export function backupMarkup(backup) {
  if (!backup) return '<p class="text-sm text-slate-500">Todavía no se ha creado un respaldo desde este módulo.</p>';
  if (!backup.verified) return `<p class="text-sm text-rose-700">${esc(backup.error || 'No se pudo verificar el último respaldo.')}</p>`;
  const download = /^\/api\/infraestructura\/backups\/preventiti_\d{8}_\d{6}_[a-f0-9]{12}\.zip\/download$/.test(backup.download_url || '') ? backup.download_url : null;
  return `<div class="space-y-3 text-sm">
    <p class="font-semibold text-emerald-800">Archivo creado · integridad comprobada</p>
    <p class="font-mono break-all text-xs">${esc(backup.filename)}</p>
    <p class="text-slate-600">${esc(dateLabel(backup.created_at))} (Uruguay) · ${formatBytes(backup.bytes)} · ${Number(backup.storage_files)} archivos de storage</p>
    <p class="text-xs text-slate-500">${esc(backup.scope)}</p>
    <div class="text-xs text-slate-500">SHA-256 <code class="block break-all mt-1">${esc(backup.sha256)}</code></div>
    ${download ? `<a href="${esc(download)}" download class="inline-flex items-center gap-2 rounded-lg bg-emerald-700 hover:bg-emerald-800 text-white px-4 py-2 text-xs font-semibold"><i data-lucide="download" class="w-4 h-4"></i>Descargar respaldo</a>` : ''}
  </div>`;
}

export function createInfrastructure({ db } = {}) {
  function renderInfraestructuraView(container) {
    container.innerHTML = `<div id="infrastructure-view" class="space-y-6">
      <header class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div><h1 class="text-2xl font-bold text-slate-900 tracking-tight">Infraestructura y respaldos</h1>
          <p class="text-sm text-slate-500 mt-1">Lecturas del servidor que ejecuta PreventiTI. Actualiza para obtener una nueva medición.</p></div>
        <button type="button" id="infra-refresh" class="px-4 py-2 bg-white border border-slate-300 rounded-lg text-xs font-semibold hover:bg-slate-50">Actualizar estado</button>
      </header>
      <section aria-label="Estado del servidor" class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
        <div id="infra-status" aria-live="polite"><p class="text-sm text-slate-500">Consultando el servidor…</p></div>
      </section>
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <section class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm space-y-4">
          <h2 class="font-bold text-slate-900">Conectividad TCP</h2>
          <p id="infra-target" class="text-xs text-slate-600">Destino pendiente de consultar.</p>
          <p class="text-xs text-slate-500">La prueba parte del servidor de la aplicación. No verifica el acceso desde VLAN 10 ni confirma las reglas de pfSense o el estado de WireGuard.</p>
          <button type="button" id="infra-scan" disabled class="px-4 py-2 rounded-lg bg-slate-900 text-white text-xs font-semibold disabled:opacity-50">Comprobar puertos</button>
          <div id="infra-scan-result" role="status" class="text-sm text-slate-500">No se ha realizado una comprobación en esta vista.</div>
        </section>
        <section class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm space-y-4">
          <h2 class="font-bold text-slate-900">Respaldo de datos</h2>
          <p id="infra-backup-scope" class="text-xs text-slate-500">Consultando disponibilidad…</p>
          <button type="button" id="infra-backup" disabled class="px-4 py-2 rounded-lg bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-semibold disabled:opacity-50">Crear respaldo</button>
          <div id="infra-backup-result" aria-live="polite"></div>
          <p class="text-xs text-slate-500">Los respaldos anteriores se conservan. Descarga la copia y guárdala en otra ubicación para protegerla ante una falla de este servidor.</p>
        </section>
      </div>
      <details class="bg-white rounded-xl border border-slate-200 p-5 text-sm text-slate-600">
        <summary class="cursor-pointer font-bold text-slate-800">Topología de referencia del laboratorio</summary>
        <div class="grid md:grid-cols-3 gap-4 mt-4">
          <div><h3 class="font-semibold">VLAN 10 · Clientes</h3><p class="font-mono text-xs mt-2">192.168.10.0/24</p><p class="text-xs mt-2">HTTPS TCP/443 hacia 192.168.20.10.</p></div>
          <div><h3 class="font-semibold">pfSense · Dos reglas inter-VLAN</h3><p class="text-xs mt-2">Permitir TCP/443 al servidor web. Bloquear TCP/22 y TCP/3306 desde VLAN 10.</p></div>
          <div><h3 class="font-semibold">VLAN 20 · Servidores</h3><p class="font-mono text-xs mt-2">192.168.20.0/24</p><p class="text-xs mt-2">Ubuntu .10 y Windows AD DS .20. VPN administrativa WireGuard: 10.10.0.0/24; peer del jefe técnico 10.10.0.2.</p></div>
        </div>
        <p class="text-xs mt-4">Configuración documentada del laboratorio; no es una lectura automática de los equipos de red.</p>
      </details>
    </div>`;
    const root = container.querySelector('#infrastructure-view');
    const find = id => root.querySelector(`#${id}`);
    const api = window.PreventiTIAPI;
    let loading = false, scanning = false, backingUp = false;

    async function refresh() {
      if (loading) return;
      loading = true;
      find('infra-refresh').disabled = true;
      try {
        const { data } = await api.request('/infraestructura/status');
        if (!root.isConnected) return;
        const metrics = [
          ['Servidor', `${data.server.hostname} · ${data.server.os}`, `PHP ${data.server.php}`],
          ['Base de datos', data.database.available ? `${data.database.driver} · Disponible` : 'Sin conexión', data.database.available ? `Consulta de lectura: ${data.database.latency_ms} ms` : 'No se pudo consultar la base.'],
          ['Almacenamiento', data.disk ? `${data.disk.used_percent}% en uso` : 'No disponible', data.disk ? `${formatBytes(data.disk.free_bytes)} libres de ${formatBytes(data.disk.total_bytes)}` : 'El entorno no proporciona esta lectura.'],
          ['Carga del sistema', data.load_average ? data.load_average.map(n => Number(n).toFixed(2)).join(' / ') : 'No disponible', 'Promedio de carga a 1, 5 y 15 minutos; no es porcentaje de CPU.'],
          ['Memoria PHP', formatBytes(data.php_memory_bytes), 'Uso de esta petición; no representa toda la RAM del servidor.'],
        ];
        find('infra-status').innerHTML = `<p class="text-xs text-slate-500 mb-4">Medido: ${esc(dateLabel(data.measured_at))} (Uruguay)</p><div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-4">${metrics.map(([title, value, description]) => `<div class="rounded-lg bg-slate-50 p-4"><h3 class="text-xs font-semibold text-slate-500">${esc(title)}</h3><p class="font-bold text-slate-900 mt-1 break-words">${esc(value)}</p><p class="text-xs text-slate-500 mt-2">${esc(description)}</p></div>`).join('')}</div>`;
        find('infra-target').textContent = `Destino: ${data.network.host} · Puertos: ${data.network.ports.join(', ')} · Origen: ${data.network.source}`;
        find('infra-scan').disabled = scanning;
        find('infra-backup-scope').textContent = data.backup_support.available ? data.backup_support.scope : data.backup_support.reason;
        find('infra-backup').disabled = backingUp || !data.backup_support.available;
        find('infra-backup-result').innerHTML = backupMarkup(data.latest_backup);
        window.lucide?.createIcons();
      } catch (error) {
        if (!root.isConnected) return;
        find('infra-status').textContent = `No se pudo actualizar el estado: ${error.message}`;
        find('infra-scan').disabled = true;
        find('infra-backup').disabled = true;
      } finally {
        loading = false;
        if (root.isConnected) find('infra-refresh').disabled = false;
      }
    }
    find('infra-refresh').addEventListener('click', refresh);
    find('infra-scan').addEventListener('click', async () => {
      if (scanning) return;
      scanning = true;
      find('infra-scan').disabled = true;
      find('infra-scan-result').textContent = 'Comprobando conectividad TCP…';
      try {
        const { data } = await api.scanNetwork();
        if (!root.isConnected) return;
        find('infra-scan-result').innerHTML = `<p class="text-xs mb-3">${esc(data.host)} · ${esc(dateLabel(data.measured_at))} (Uruguay)<br>Origen: ${esc(data.source)}</p><ul class="space-y-2">${data.ports.map(port => `<li class="rounded-lg p-3 ${port.reachable ? 'bg-emerald-50 text-emerald-900' : 'bg-amber-50 text-amber-900'}"><strong>TCP/${Number(port.port)} · ${port.reachable ? 'Conexión establecida' : 'Sin conexión'}</strong><p class="text-xs mt-1">${esc(port.detail)} (${Number(port.latency_ms)} ms)</p></li>`).join('')}</ul>`;
      } catch (error) {
        if (root.isConnected) find('infra-scan-result').textContent = `No se pudo completar la comprobación: ${error.message}`;
      } finally {
        scanning = false;
        if (root.isConnected) find('infra-scan').disabled = false;
      }
    });
    find('infra-backup').addEventListener('click', async () => {
      if (backingUp) return;
      backingUp = true;
      find('infra-backup').disabled = true;
      find('infra-backup-result').textContent = 'Creando la copia y comprobando su integridad…';
      try {
        const response = await api.runDrpBackup();
        if (root.isConnected) {
          find('infra-backup-result').innerHTML = backupMarkup(response.backup);
          window.lucide?.createIcons();
        }
        window.showToast('Respaldo creado', response.warning || response.message, response.warning ? 'warning' : 'success');
        if (db) {
          try {
            const audit = await api.request('/dashboard/audit-logs');
            db.auditLogs = audit.data.map(item => ({ id: item.id, timestamp: item.fecha_auditoria, user_name: 'Registro del sistema', action: 'Auditoría', detail: item.descripcion }));
          } catch (_) { /* La copia ya está creada; la auditoría podrá recargarse después. */ }
        }
      } catch (error) {
        if (root.isConnected) find('infra-backup-result').textContent = `No se creó un respaldo verificable: ${error.message}`;
      } finally {
        backingUp = false;
        if (root.isConnected) find('infra-backup').disabled = false;
      }
    });
    refresh();
  }
  return { renderInfraestructuraView };
}
