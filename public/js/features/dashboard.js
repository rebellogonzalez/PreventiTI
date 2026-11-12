import { escapeHTML } from '../lib/html.js';
import { dayTasks, todayDate } from '../lib/calendar-dates.js';
import { formatTimestamp } from '../lib/timestamps.js';

export function createDashboard({ db, getCurrentUser }) {
  let showAllAlerts = false;
  function renderDashboardView(container) {
    if (getCurrentUser().role === 'empleado') {
      const open = db.reports.filter(report => !report.closed && !['Finalizado', 'Cerrado', 'Cancelado', 'Resuelto'].includes(report.status)).length;
      container.innerHTML = `<div class="space-y-6"><div><h1 class="text-2xl font-bold text-slate-900">Mi espacio de trabajo</h1><p class="text-sm text-slate-500 mt-2">Registra una falla y consulta el seguimiento de tus reportes.</p></div><div class="flex gap-3"><button onclick="window.openModal('modal-nuevo-reporte')" class="px-4 py-2 rounded-lg bg-sky-600 text-white font-semibold">Crear reporte</button><a href="#reportes" class="px-4 py-2 rounded-lg border bg-white">Ver mis reportes</a></div><div class="grid sm:grid-cols-2 gap-4"><div class="bg-white border rounded-xl p-5"><p>Mis reportes</p><strong class="text-3xl">${db.reports.length}</strong></div><div class="bg-white border rounded-xl p-5"><p>En seguimiento</p><strong class="text-3xl">${open}</strong></div></div></div>`;
      return;
    }
    const totalAssets = db.assets.length;
    const activeAssets = window.PreventiTIInventory.selectAssets(db.assets, { status: 'normal' }).length;
    const attentionAssets = window.PreventiTIInventory.selectAssets(db.assets, { status: 'atencion' }).length;
    const criticalAssets = window.PreventiTIInventory.selectAssets(db.assets, { status: 'critica' }).length;
    const reports = db.reports || [];
    const notifications = db.notifications || [];
    const unreadNotifications = notifications.filter(item => !item.read_at).length;

    // Tareas del día de hoy
    const todayTasks = dayTasks(db.calendarTasks || [], todayDate()).filter(task => !task.cancel_reason);

    // Consumibles críticos (<= 20%)
    const lowConsumables = db.assets.filter(a => a.has_consumable && a.consumable_level !== null && a.consumable_level <= 20);

    // Próximos mantenimientos a vencer (días <= 14)
    const upcomingMaintenances = db.assets
      .filter(a => a.days_to_maintenance !== null && a.days_to_maintenance <= 14 && a.days_to_maintenance >= 0)
      .sort((a, b) => a.days_to_maintenance - b.days_to_maintenance)
      .slice(0, 5);

    container.innerHTML = `
      <div class="space-y-6">
        <!-- Encabezado del Tablero -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <div class="flex items-center gap-2">
              <span class="inline-block w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
              <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Centro de Control Operativo</h1>
            </div>
            <p class="text-sm text-slate-500 mt-1">Supervisión de activos, mantenimiento preventivo, reportes y notificaciones operativas.</p>
          </div>
          <div class="flex items-center gap-2 flex-wrap">
            <button onclick="window.openModal('modal-nuevo-reporte')" class="inline-flex items-center gap-2 px-3.5 py-2 bg-sky-600 hover:bg-sky-700 text-white rounded-lg text-xs font-semibold shadow-sm transition">
              <i data-lucide="plus-circle" class="w-4 h-4"></i> Reportar Falla
            </button>
            <button data-permission="can_maintain_assets" onclick="window.openInspection()" class="inline-flex items-center gap-2 px-3.5 py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-lg text-xs font-semibold shadow-sm transition">
              <i data-lucide="wrench" class="w-4 h-4"></i> Cargar Service
            </button>
            <a href="#calendario" class="inline-flex items-center gap-2 px-3.5 py-2 bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 rounded-lg text-xs font-semibold shadow-sm transition">
              <i data-lucide="calendar" class="w-4 h-4 text-sky-600"></i> Ver Calendario
            </a>
            <a data-permission="can_view_all_stats" href="#estadisticas" class="inline-flex items-center gap-2 px-3.5 py-2 bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 rounded-lg text-xs font-semibold shadow-sm transition">
              <i data-lucide="bar-chart-3" class="w-4 h-4 text-purple-600"></i> Ver Estadísticas
            </a>
          </div>
        </div>

        <nav class="section-index" aria-label="Secciones del panel principal"><span>En esta página</span><button type="button" onclick="window.scrollToSection('panel-resumen')">Resumen</button><button type="button" onclick="window.scrollToSection('panel-avisos')">Avisos</button><button type="button" onclick="window.scrollToSection('panel-agenda')">Planificación</button><button data-permission="can_view_audit" type="button" onclick="window.scrollToSection('panel-actividad')">Actividad</button></nav>

        <section id="panel-resumen" class="workspace-section" tabindex="-1" aria-labelledby="panel-resumen-title">
          <div class="workspace-section-heading">
            <h2 id="panel-resumen-title">Resumen operativo</h2>
            <p>Estado del parque y accesos al inventario.</p>
          </div>
        <!-- Fila de Tarjetas KPI -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <!-- KPI 1: Activos Totales -->
          <a href="#inventario" data-inventory-shortcut="todos" onclick="window.filterInventarioByAlert('todos')" aria-label="Parque total: ${totalAssets} dispositivos. Ver inventario completo" class="block bg-white rounded-xl p-4 border border-slate-200 shadow-sm hover:border-sky-400 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500 focus-visible:ring-offset-2 transition">
            <div class="flex items-center justify-between">
              <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Parque Total</span>
              <span class="p-2 rounded-lg bg-slate-100 text-slate-700"><i data-lucide="server" class="w-4 h-4"></i></span>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
              <span class="text-3xl font-black text-slate-900">${totalAssets}</span>
              <span class="text-xs text-emerald-600 font-semibold">${activeAssets} operativos</span>
            </div>
            <div class="mt-2 text-xs text-slate-500 flex items-center justify-between">
              <span>${db.categories.length} categorías</span>
              <span class="text-sky-600 font-semibold">Ver inventario &rarr;</span>
            </div>
          </a>

          <!-- KPI 2: Alertas de Atención (Amarillo) -->
          <a href="#inventario" data-inventory-shortcut="atencion" onclick="window.filterInventarioByAlert('atencion')" aria-label="En atención: ${attentionAssets} dispositivos. Ver inventario filtrado" class="block bg-white rounded-xl p-4 border border-amber-200 shadow-sm hover:border-amber-400 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500 focus-visible:ring-offset-2 transition">
            <div class="flex items-center justify-between">
              <span class="text-xs font-bold text-amber-700 uppercase tracking-wider">En atención</span>
              <span class="p-2 rounded-lg bg-amber-50 text-amber-600"><i data-lucide="alert-triangle" class="w-4 h-4"></i></span>
            </div>
            <div class="mt-2 flex flex-wrap items-baseline gap-2">
              <span class="text-3xl font-black text-amber-600">${attentionAssets}</span>
              <span class="text-xs text-amber-700 font-semibold">&le; 7 días o insumo &le; 20%</span>
            </div>
            <div class="mt-2 text-xs text-amber-700 flex flex-wrap gap-2 items-center justify-between">
              <span>Riesgo preventivo medio</span>
              <span class="font-bold text-amber-800 whitespace-nowrap">Ver dispositivos &rarr;</span>
            </div>
          </a>

          <!-- KPI 3: Alertas Críticas (Rojo) -->
          <a href="#inventario" data-inventory-shortcut="critica" onclick="window.filterInventarioByAlert('critica')" aria-label="Estado crítico: ${criticalAssets} dispositivos. Ver inventario filtrado" class="block bg-white rounded-xl p-4 border border-rose-200 shadow-sm hover:border-rose-400 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500 focus-visible:ring-offset-2 transition">
            <div class="flex items-center justify-between">
              <span class="text-xs font-bold text-rose-700 uppercase tracking-wider">Estado crítico</span>
              <span class="p-2 rounded-lg bg-rose-50 text-rose-600"><i data-lucide="alert-octagon" class="w-4 h-4"></i></span>
            </div>
            <div class="mt-2 flex flex-wrap items-baseline gap-2">
              <span class="text-3xl font-black text-rose-600">${criticalAssets}</span>
              <span class="text-xs text-rose-700 font-semibold">Vencidos o fuera de servicio</span>
            </div>
            <div class="mt-2 text-xs text-rose-700 flex flex-wrap gap-2 items-center justify-between">
              <span>Atención prioritaria</span>
              <span class="font-bold text-rose-800 whitespace-nowrap">Ver dispositivos &rarr;</span>
            </div>
          </a>

          <!-- KPI 4: Reportes -->
          <div class="bg-white rounded-xl p-4 border border-slate-200 shadow-sm hover:border-slate-300 transition">
            <div class="flex items-center justify-between">
              <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Reportes</span>
              <span class="p-2 rounded-lg bg-sky-50 text-sky-600"><i data-lucide="file-text" class="w-4 h-4"></i></span>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
              <span class="text-3xl font-black text-slate-900">${reports.length}</span>
              <span data-permission="can_view_notifications" class="text-xs text-slate-500 font-medium">${unreadNotifications} notificaciones sin leer</span>
            </div>
            <div class="mt-2 text-xs text-slate-500 flex items-center justify-between">
              <span>Registro auditable</span>
              <a href="#reportes" class="text-sky-600 font-semibold hover:underline">Ver reportes &rarr;</a>
            </div>
          </div>
        </div>

        <!-- Banner del Semáforo Preventivo Interactivo (Reglas de Negocio) -->
        <div class="bg-slate-900 rounded-xl p-4 text-white shadow-sm border border-slate-800">
          <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
            <div>
              <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse"></span>
                <h3 class="text-sm font-bold text-white">Semáforo Preventivo Determinista</h3>
              </div>
              <p class="text-xs text-slate-300 mt-0.5">
                Filtra los ${totalAssets} dispositivos por su estado actual:
              </p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
              <button onclick="window.filterInventarioByAlert('todos')" class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-xs font-medium text-slate-200 transition">
                Todos (${totalAssets})
              </button>
              <button onclick="window.filterInventarioByAlert('normal')" class="px-3 py-1.5 rounded-lg bg-emerald-900/60 hover:bg-emerald-900 text-emerald-300 border border-emerald-700 text-xs font-semibold transition flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-emerald-400"></span> Operativos (${activeAssets})
              </button>
              <button onclick="window.filterInventarioByAlert('atencion')" class="px-3 py-1.5 rounded-lg bg-amber-900/60 hover:bg-amber-900 text-amber-300 border border-amber-700 text-xs font-semibold transition flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-amber-400 animate-ping"></span> En atención (${attentionAssets})
              </button>
              <button onclick="window.filterInventarioByAlert('critica')" class="px-3 py-1.5 rounded-lg bg-rose-900/60 hover:bg-rose-900 text-rose-300 border border-rose-700 text-xs font-semibold transition flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-rose-400"></span> Críticos (${criticalAssets})
              </button>
            </div>
          </div>
        </div>

        </section>

        <section id="panel-avisos" class="workspace-section" tabindex="-1" aria-labelledby="panel-avisos-title">
          <div class="workspace-section-heading">
            <h2 id="panel-avisos-title">Avisos y atención</h2>
            <p>Reportes recibidos e insumos que requieren revisión.</p>
          </div>
          <div class="workspace-card-grid">
        <section data-permission="can_view_notifications" id="panel-notificaciones" class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
          <div class="p-4 border-b border-slate-100 flex items-center justify-between">
            <div>
              <h3 class="font-bold text-slate-900 text-sm flex items-center gap-2"><i data-lucide="bell" class="w-4 h-4 text-amber-500"></i> Notificaciones</h3>
              <p class="text-xs text-slate-500 mt-0.5">Nuevos reportes destinados al usuario autenticado.</p>
            </div>
            <span class="text-xs font-semibold text-slate-500">${unreadNotifications} sin leer</span>
          </div>
          <div class="divide-y divide-slate-100">
            ${notifications.length === 0 ? '<div class="p-6 text-center text-xs text-slate-400">No hay notificaciones para este perfil.</div>' : (showAllAlerts ? notifications : notifications.slice(0, 8)).map(item => `
              <button onclick="window.openNotification(${item.id})" class="w-full p-4 text-left hover:bg-slate-50 transition ${item.read_at ? '' : 'bg-amber-50/40'}">
                <div class="flex items-start justify-between gap-4">
                  <div>
                    <div class="text-xs font-bold text-slate-900">${escapeHTML(item.title)}</div>
                    <p class="text-xs text-slate-600 mt-1">${escapeHTML(item.message)}</p>
                  </div>
                  <span class="text-[10px] text-slate-400 whitespace-nowrap">${escapeHTML(item.created_at || '')}</span>
                </div>
              </button>
            `).join('')}
          </div>
          ${notifications.length > 8 ? `<button type="button" id="toggle-all-alerts" class="report-button report-button--secondary m-3" aria-expanded="${showAllAlerts}">${showAllAlerts ? 'Ver últimas 8' : 'Ver todas las alertas (' + notifications.length + ')'}</button>` : ''}
        </section>

          <!-- Insumos y Suministros en Umbral de Alerta -->
          <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-sm flex flex-col justify-between">
            <div>
              <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div class="flex items-center gap-2">
                  <i data-lucide="droplet" class="w-4 h-4 text-amber-600"></i>
                  <h3 class="text-sm font-bold text-slate-800">Insumos Críticos en Advertencia (&le; 20%)</h3>
                </div>
                <span class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 text-[11px] font-bold">${lowConsumables.length} equipos</span>
              </div>
              <div class="mt-3 space-y-3">
                ${lowConsumables.map(c => `
                  <div class="p-3 rounded-lg border border-amber-200 bg-amber-50/50">
                    <div class="flex items-center justify-between text-xs">
                      <span class="font-semibold text-slate-900">${escapeHTML(c.name)}</span>
                      <span class="font-bold text-amber-800 font-mono">${c.consumable_level}%</span>
                    </div>
                    <div class="mt-1 flex items-center justify-between text-[11px] text-slate-500">
                      <span>Insumo: <b>${escapeHTML(c.consumable_type)}</b></span>
                      <span>${escapeHTML(c.location_name)}</span>
                    </div>
                    <div class="progress-bar-wrap mt-2 bg-amber-200">
                      <div class="progress-bar-fill bg-amber-500" style="width: ${c.consumable_level}%;"></div>
                    </div>
                    <div class="mt-2 text-right">
                      <button onclick="window.openModalConsumable(${c.id})" class="text-[11px] text-sky-700 font-bold hover:underline">
                        Reponer Insumo Ahora &rarr;
                      </button>
                    </div>
                  </div>
                `).join('')}
              </div>
            </div>
            <div class="mt-4 pt-3 border-t border-slate-100 text-right">
              <a href="#consumibles" class="text-xs text-sky-600 font-semibold hover:underline">Ver Todos los Insumos &rarr;</a>
            </div>
          </div>
          </div>
        </section>

        <section id="panel-agenda" class="workspace-section" tabindex="-1" aria-labelledby="panel-agenda-title">
          <div class="workspace-section-heading">
            <h2 id="panel-agenda-title">Planificación y mantenimiento</h2>
            <p>Tareas programadas y próximas revisiones.</p>
          </div>
          <div class="workspace-card-grid">
          <!-- Tareas del Día (Agenda Inmediata) -->
          <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-sm flex flex-col justify-between">
            <div>
              <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div class="flex items-center gap-2">
                  <i data-lucide="clock" class="w-4 h-4 text-sky-600"></i>
                  <h3 class="text-sm font-bold text-slate-800">Tareas programadas para hoy</h3>
                </div>
                <span class="px-2 py-0.5 rounded-full bg-sky-100 text-sky-800 text-[11px] font-bold">${todayTasks.length} tareas</span>
              </div>
              <div class="mt-3 space-y-2.5">
                ${todayTasks.length === 0 ? '<div class="text-xs text-slate-400 py-4 text-center">No hay tareas pendientes para hoy.</div>' : todayTasks.map(t => `
                  <div class="p-3 rounded-lg border ${t.priority === 'Critica' ? 'border-rose-200 bg-rose-50/50' : 'border-slate-100 bg-slate-50/70'} flex items-start justify-between gap-2 text-xs">
                    <div class="flex-1 min-w-0">
                      <div class="flex items-center gap-2">
                        <span class="font-mono text-[11px] font-bold text-slate-500">${escapeHTML(t.time || '—')}</span>
                        <span class="font-semibold text-slate-900 truncate">${escapeHTML(t.title)}</span>
                      </div>
                      <div class="mt-1 flex items-center gap-2 text-[11px] text-slate-500">
                        <span class="font-medium text-slate-700">Resp: ${escapeHTML(t.technician || 'Sin asignar')}</span>
                        <span>&bull;</span>
                        <span class="font-mono">${escapeHTML(t.asset)}</span>
                      </div>
                    </div>
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold ${
                      t.priority === 'Critica' ? 'bg-rose-100 text-rose-800' :
                      t.priority === 'Alta' ? 'bg-amber-100 text-amber-800' : 'bg-slate-200 text-slate-700'
                    }">${escapeHTML(t.priority)}</span>
                  </div>
                `).join('')}
              </div>
            </div>
            <div class="mt-4 pt-3 border-t border-slate-100 text-right">
              <a href="#calendario" class="text-xs text-sky-600 font-semibold hover:underline">Abrir Calendario Completo de Tareas &rarr;</a>
            </div>
          </div>

          <!-- Próximos Vencimientos de Mantenimiento -->
          <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-sm">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
              <div class="flex items-center gap-2">
                <i data-lucide="calendar-clock" class="w-4 h-4 text-sky-600"></i>
                <h3 class="text-sm font-bold text-slate-800">Próximos Vencimientos de Mantenimiento</h3>
              </div>
              <a href="#calendario" class="text-xs text-sky-600 font-medium hover:underline">Ver agenda &rarr;</a>
            </div>
            <div class="mt-3 divide-y divide-slate-100">
              ${upcomingMaintenances.map(m => `
                <div class="py-2.5 flex items-center justify-between gap-3 text-xs">
                  <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                      <span class="font-semibold text-slate-900 truncate">${escapeHTML(m.name)}</span>
                      <span class="text-[11px] text-slate-400 font-mono">${escapeHTML(m.code)}</span>
                    </div>
                    <div class="text-[11px] text-slate-500 mt-0.5 flex items-center gap-3">
                      <span>Ubicación: ${escapeHTML(m.location_name)}</span>
                      <span>Resp.: ${escapeHTML(m.responsible)}</span>
                    </div>
                  </div>
                  <div class="text-right flex-shrink-0">
                    <span class="inline-block px-2 py-0.5 rounded text-[11px] font-bold ${
                      m.days_to_maintenance <= 7 ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700'
                    }">
                      En ${m.days_to_maintenance} días
                    </span>
                    <div class="text-[10px] text-slate-400 mt-0.5 font-mono">${escapeHTML(m.next_maintenance)}</div>
                  </div>
                </div>
              `).join('')}
            </div>
          </div>

          </div>
        </section>

        <section data-permission="can_view_audit" id="panel-actividad" class="workspace-section" tabindex="-1" aria-labelledby="panel-actividad-title">
          <div class="workspace-section-heading">
            <h2 id="panel-actividad-title">Actividad del sistema</h2>
            <p>Registro de acciones y seguimiento de auditoría.</p>
          </div>
          <!-- Bitácora de Auditoría en Tiempo Real (RF-12) -->
          <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-sm">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
              <div class="flex items-center gap-2">
                <i data-lucide="shield-check" class="w-4 h-4 text-emerald-600"></i>
                <h3 class="text-sm font-bold text-slate-800">Bitácora de Auditoría Inalterable (RF-12)</h3>
              </div>
              <span class="text-xs text-slate-400">Hora del servidor (UTC)</span>
            </div>
            <div class="mt-3 divide-y divide-slate-100 max-h-[260px] overflow-y-auto pr-1">
              ${db.auditLogs.slice(0, 6).map(log => `
                <div class="py-2 text-xs">
                  <div class="flex items-center justify-between text-slate-700">
                    <span class="font-semibold text-slate-900">${escapeHTML(log.user_name)}</span>
                    <span class="font-mono text-[10px] text-slate-400">${escapeHTML(formatTimestamp(log.timestamp, { timeZone: 'UTC' }))}</span>
                  </div>
                  <div class="text-slate-600 mt-0.5">
                    <span class="px-1.5 py-0.5 rounded bg-slate-100 font-mono text-[10px] font-semibold text-slate-700 mr-1">${escapeHTML(log.action)}</span>
                    ${escapeHTML(log.detail)}
                  </div>
                </div>
              `).join('')}
            </div>
          </div>
        </section>
      </div>
    `;
    const toggle = container.querySelector?.('#toggle-all-alerts');
    toggle?.addEventListener('click', () => {
      showAllAlerts = !showAllAlerts;
      window.renderCurrentRoute();
      document.getElementById('toggle-all-alerts')?.focus();
    });
  }

  return { renderDashboardView };
}
