import { escapeHTML as reportDisplayText } from '../lib/html.js';
import { renderTechnicianHistory } from './technician-history.js';

const formatStatsPrice = (amount, currency = 'UYU') => amount == null ? 'Sin registrar' :
  `${Number(amount).toLocaleString('es-UY', { minimumFractionDigits: 0, maximumFractionDigits: 2 })} ${currency}`;

export function createStatistics({ db }) {
  function renderEstadisticasView(container) {
    const budget = db.budgetMetrics || {};
    const price = amount => formatStatsPrice(amount, budget.currency || 'UYU');
    const assetCount = (db.categories || []).reduce((sum, category) => sum + Number(category.count || 0), 0);

    container.innerHTML = `
      <div class="space-y-6">
        <!-- Encabezado de Estadísticas -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <div class="flex items-center gap-2">
              <i data-lucide="bar-chart-3" class="w-6 h-6 text-purple-600"></i>
              <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Estadísticas y costos</h1>
            </div>
            <p class="text-sm text-slate-500 mt-0.5">Presupuestos, costos de mantenimiento, reportes por técnico y distribución del parque tecnológico.</p>
          </div>
          <div class="flex items-center gap-2">
            <a href="#analisis" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-purple-50 text-purple-800 border border-purple-200 hover:bg-purple-100 text-xs font-semibold">
              <i data-lucide="activity" class="w-4 h-4"></i> Incidencias y repuestos &rarr;
            </a>
          </div>
        </div>

        <!-- Módulo Financiero: Presupuestos y Costos Estimados -->
        <div class="bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 rounded-xl p-5 text-white shadow-sm border border-slate-800">
          <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 pb-4 border-b border-slate-800">
            <div>
              <span class="text-xs font-bold text-sky-400 uppercase tracking-wider">Control de gastos de mantenimiento</span>
              <h3 class="text-lg font-bold text-white mt-1">Presupuesto Anual de Mantenimiento y Repuestos</h3>
            </div>
            <div class="text-right">
              <div class="text-xs text-slate-400">Gasto registrado por activo del catálogo</div>
              <div class="text-xl font-extrabold text-emerald-400">${price(assetCount ? (budget.executed_to_date || 0) / assetCount : null)}</div>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-4 text-xs">
            <div class="p-3 rounded-lg bg-white/5 border border-white/10">
              <span class="text-slate-400">Presupuesto Asignado</span>
              <div class="text-lg font-bold text-white mt-1">${price(budget.annual_budget)}</div>
              <div class="text-[10px] text-slate-400 mt-1">Ejercicio ${reportDisplayText(budget.year || '')}</div>
            </div>
            <div class="p-3 rounded-lg bg-white/5 border border-white/10">
              <span class="text-slate-400">Ejecutado a la Fecha</span>
              <div class="text-lg font-bold text-amber-400 mt-1">${price(budget.executed_to_date)}</div>
              <div class="text-[10px] text-amber-300 mt-1">${budget.annual_budget ? `${Number(budget.execution_percent || 0).toLocaleString('es-UY')}% del presupuesto anual` : 'Sin presupuesto asignado'}</div>
            </div>
            <div class="p-3 rounded-lg bg-white/5 border border-white/10">
              <span class="text-slate-400">Remanente Disponible</span>
              <div class="text-lg font-bold text-emerald-400 mt-1">${price(budget.remaining_budget)}</div>
              <div class="text-[10px] text-emerald-300 mt-1">Presupuesto menos gastos registrados</div>
            </div>
          </div>
          <p class="text-xs text-slate-300 mt-4">${reportDisplayText(budget.scope || '')}</p>
          <div class="flex flex-wrap gap-4 text-xs text-slate-300 mt-3">${(budget.breakdown || []).map(item => `<span>${reportDisplayText(item.item)}: <b>${price(item.amount)}</b></span>`).join('')}</div>
        </div>

        ${window.PreventiTIAPI?.reportHistory ? '<div data-technician-reports role="status">Cargando historial de técnicos…</div>' : renderTechnicianHistory(db.serverStats?.technicians_ranking || [], db.reports || [])}

        <!-- Gráficos Principales (Chart.js) -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <!-- Gráfico 1: Activos por Categoría -->
          <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-sm flex flex-col">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
              <h3 class="text-sm font-bold text-slate-800">Distribución del Parque (${assetCount} Activos / ${(db.categories || []).length} Categorías)</h3>
              <span class="text-xs text-slate-400">Gráfico de Dona</span>
            </div>
            <div class="relative flex-1 min-h-[240px] pt-3 flex items-center justify-center">
              <canvas id="chart-categorias-stats"></canvas>
            </div>
          </div>

          <!-- Gráfico 2: Mantenimientos por Hito -->
          <div class="bg-white rounded-xl p-5 border border-slate-200 shadow-sm flex flex-col">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
              <h3 class="text-sm font-bold text-slate-800">Mantenimientos registrados y tareas pendientes por mes</h3>
              <span class="text-xs text-slate-400">Últimos meses y próxima agenda</span>
            </div>
            <div class="relative flex-1 min-h-[240px] pt-3">
              <canvas id="chart-mantenimientos-stats"></canvas>
            </div>
          </div>
        </div>


      </div>
    `;

    const history = container.querySelector?.('[data-technician-reports]');
    if (history) {
      async function loadHistory() {
        try {
          const response = await window.PreventiTIAPI.request('/reportes');
          if (history.isConnected) history.outerHTML = renderTechnicianHistory(db.serverStats?.technicians_ranking || [], response.data);
        } catch (error) {
          history.textContent = 'No se pudo cargar el historial de técnicos. ';
          const retry = document.createElement('button');
          retry.textContent = 'Reintentar';
          retry.onclick = loadHistory;
          history.append(retry);
        }
      }
      loadHistory();
    }
    setTimeout(() => {
      initEstadisticasCharts();
    }, 50);
  }

  // Análisis de incidencias y repuestos, con los permisos de Estadísticas.
  function renderAnalisisView(container) {
    const components = [...(db.componentsReplaced || [])].sort((a, b) => b.count - a.count);
    const recurring = db.recurringIncidents || [];
    const esc = reportDisplayText;

    // Calcular incidencias por usuario
    const usersById = new Map(db.users.map(user => [String(user.id), user]));
    const userIncidents = new Map();
    for (const report of db.reports || []) {
      const key = report.creator_id == null ? `legacy:${report.creator_name || ''}` : String(report.creator_id);
      if (!userIncidents.has(key)) {
        const user = usersById.get(key);
        userIncidents.set(key, {
          name: user?.name || report.creator_name || 'Usuario sin identificar', email: user?.email,
          role: user?.role_title || report.creator_role || 'Sin rol registrado', inactive: user?.active === false,
          sector: user?.sector || 'Sin sector registrado', count: 0,
        });
      }
      userIncidents.get(key).count++;
    }
    const incidentsByUser = [...userIncidents.values()].sort((a, b) => b.count - a.count);

    // Calcular incidencias por dispositivo
    const assetIncidentMap = Object.create(null);
    const reportsWithoutDevice = (db.reports || []).filter(t => !t.asset_code).length;
    (db.reports || []).forEach(t => {
      if (!t.asset_code) return;
      assetIncidentMap[t.asset_code] = (assetIncidentMap[t.asset_code] || 0) + 1;
    });
    const incidentsByAsset = Object.entries(assetIncidentMap)
      .map(([code, count]) => {
        const a = db.assets.find(ast => ast.code === code) || db.archivedAssets?.find(ast => ast.code === code);
        return { code, count, name: a ? a.name : code, location: a ? a.location_name : 'Sin ubicación registrada' };
      })
      .sort((a, b) => b.count - a.count);

    container.innerHTML = `
      <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <div class="flex items-center gap-2">
              <i data-lucide="activity" class="w-6 h-6 text-sky-600 shrink-0"></i>
              <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Incidencias y repuestos</h1>
            </div>
            <p class="text-sm text-slate-500 mt-1">Reemplazos más frecuentes, incidencias por usuario y dispositivo, y medidas correctivas.</p>
          </div>
          <a href="#reportes" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold shrink-0">
            <i data-lucide="file-text" class="w-4 h-4"></i> Ver reportes
          </a>
        </div>

        <nav class="section-index" aria-label="Secciones de incidencias y repuestos"><span>En esta página</span><button type="button" onclick="window.scrollToSection('analisis-repuestos')">Repuestos</button><button type="button" onclick="window.scrollToSection('analisis-reportes')">Usuarios y dispositivos</button><button type="button" onclick="window.scrollToSection('analisis-patrones')">Patrones recurrentes</button></nav>

        <!-- Sección: Componentes más Reemplazados -->
        <div id="analisis-repuestos" tabindex="-1" class="section-target"><div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
          <div class="flex flex-wrap gap-2 items-center justify-between pb-3 border-b border-slate-100">
            <div class="flex items-center gap-2">
              <i data-lucide="cpu" class="w-4 h-4 text-sky-600"></i>
              <h2 class="font-bold text-slate-900 text-sm">Componentes y Repuestos Más Reemplazados</h2>
            </div>
            <span class="text-xs text-slate-400">Reemplazos registrados en el ejercicio actual</span>
          </div>

          <div class="mt-4 overflow-x-auto">
            <table class="min-w-[760px] w-full text-left text-xs text-slate-600">
              <thead class="bg-slate-50 text-slate-700 font-semibold border-b border-slate-200">
                <tr>
                  <th class="py-2.5 px-3">Componente / Repuesto</th>
                  <th class="py-2.5 px-3">Categoría</th>
                  <th class="py-2.5 px-3 text-center">Unidades Reemplazadas</th>
                  <th class="py-2.5 px-3 text-right">Costo unitario promedio</th>
                  <th class="py-2.5 px-3 text-right">Total Invertido</th>
                  <th class="py-2.5 px-3">Motivo Principal de Reemplazo</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                ${components.length === 0 ? '<tr><td colspan="6" class="py-8 text-center text-slate-500">Todavía no hay reemplazos registrados.</td></tr>' : components.map(cmp => `
                  <tr class="hover:bg-slate-50/80 transition">
                    <td class="py-2.5 px-3 font-semibold text-slate-900">${esc(cmp.name)}</td>
                    <td class="py-2.5 px-3"><span class="px-2 py-0.5 rounded bg-slate-100 text-[11px] font-medium">${esc(cmp.category)}</span></td>
                    <td class="py-2.5 px-3 text-center">
                      <span class="px-2.5 py-0.5 rounded-full font-bold bg-sky-100 text-sky-800">${cmp.count}</span>
                    </td>
                    <td class="py-2.5 px-3 text-right font-mono">${formatStatsPrice(cmp.cost, cmp.currency)}</td>
                    <td class="py-2.5 px-3 text-right font-mono font-bold text-slate-900">${formatStatsPrice(cmp.total ?? cmp.cost * cmp.count, cmp.currency)}</td>
                    <td class="py-2.5 px-3 text-slate-500 text-[11px]">${esc(cmp.reason)}</td>
                  </tr>
                `).join('')}
              </tbody>
            </table>
          </div>
        </div>

        </div>

        <!-- Doble Columna: Incidencias por Usuario vs Incidencias por Dispositivo -->
        <div id="analisis-reportes" tabindex="-1" aria-label="Incidencias por usuario y dispositivo" class="section-target grid grid-cols-1 lg:grid-cols-2 gap-6">
          <!-- Incidencias por Usuario -->
          <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
            <div class="flex flex-wrap gap-2 items-center justify-between pb-3 border-b border-slate-100">
              <div class="flex items-center gap-2">
                <i data-lucide="user-x" class="w-4 h-4 text-amber-600"></i>
                <h2 class="font-bold text-slate-900 text-sm">Incidencias Reportadas por Usuario</h2>
              </div>
              <span class="text-xs text-slate-400">Ranking</span>
            </div>
            <div class="mt-3 divide-y divide-slate-100 max-h-72 overflow-y-auto pr-1">
              ${incidentsByUser.length === 0 ? '<p class="py-6 text-center text-xs text-slate-500">Todavía no hay incidencias reportadas por usuarios.</p>' : incidentsByUser.map((u, idx) => `
                <div class="incidence-row py-2.5 text-xs">
                  <span class="w-5 h-5 rounded-full bg-slate-100 font-bold text-slate-600 flex items-center justify-center text-[10px]">
                    ${idx + 1}
                  </span>
                  <div>
                    <div class="font-semibold text-slate-900">${esc(u.name)}</div>
                    <div class="text-[11px] text-slate-400">${esc(u.role)} &bull; ${esc(u.sector)}${u.inactive ? ' · Cuenta de baja' : ''}</div>
                    ${u.email ? `<div class="text-[11px] text-slate-500">${esc(u.email)}</div>` : ''}
                  </div>
                  <span class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-900 font-bold text-xs">
                    ${u.count} ${u.count === 1 ? 'reporte' : 'reportes'}
                  </span>
                </div>
              `).join('')}
            </div>
          </div>

          <!-- Incidencias por Dispositivo -->
          <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
            <div class="flex flex-wrap gap-2 items-center justify-between pb-3 border-b border-slate-100">
              <div class="flex items-center gap-2">
                <i data-lucide="alert-triangle" class="w-4 h-4 text-rose-600"></i>
                <h2 class="font-bold text-slate-900 text-sm">Dispositivos con Mayor Tasa de Fallas</h2>
              </div>
              <span class="text-xs text-slate-400">Por cantidad de reportes</span>
            </div>
            ${reportsWithoutDevice ? `<p class="mt-3 text-xs text-slate-500">${reportsWithoutDevice} ${reportsWithoutDevice === 1 ? 'reporte sin dispositivo identificado, excluido' : 'reportes sin dispositivo identificado, excluidos'} del ranking.</p>` : ''}
            <div class="mt-3 divide-y divide-slate-100 max-h-72 overflow-y-auto pr-1">
              ${incidentsByAsset.length === 0 ? '<p class="py-6 text-center text-xs text-slate-500">Todavía no hay incidencias asociadas a dispositivos.</p>' : incidentsByAsset.map((a, idx) => `
                <div class="incidence-row py-2.5 text-xs">
                  <span class="w-5 h-5 rounded-full bg-slate-100 font-bold text-slate-600 flex items-center justify-center text-[10px]">
                    ${idx + 1}
                  </span>
                  <div>
                    <div class="font-semibold text-slate-900">${esc(a.name)}</div>
                    <div class="text-[11px] text-slate-400 font-mono">${esc(a.code)} &bull; ${esc(a.location)}</div>
                  </div>
                  <span class="px-2 py-0.5 rounded-full bg-rose-100 text-rose-800 font-bold text-xs">
                    ${a.count} ${a.count === 1 ? 'reporte' : 'reportes'}
                  </span>
                </div>
              `).join('')}
            </div>
          </div>
        </div>

        <!-- Incidencias Recurrentes y Causa Raíz -->
        <div id="analisis-patrones" tabindex="-1" class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
          <div class="flex flex-wrap gap-2 items-center justify-between pb-3 border-b border-slate-100">
            <div class="flex items-center gap-2">
              <i data-lucide="repeat" class="w-4 h-4 text-sky-600"></i>
              <h2 class="font-bold text-slate-900 text-sm">Patrones de Incidencias Recurrentes y Medidas Correctivas</h2>
            </div>
            <span class="text-xs text-slate-400">Análisis Causa Raíz</span>
          </div>
          <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
            ${recurring.length === 0 ? '<p class="py-6 text-center text-xs text-slate-500 md:col-span-2">Todavía no hay patrones de incidencias recurrentes registrados.</p>' : recurring.map(rec => `
              <div class="p-4 rounded-lg border border-slate-200 bg-slate-50 flex flex-col justify-between">
                <div>
                  <div class="flex items-center justify-between">
                    <h4 class="font-bold text-slate-900 text-xs">${esc(rec.title)}</h4>
                    <span class="px-2 py-0.5 rounded bg-sky-100 text-sky-800 text-[10px] font-bold">${esc(rec.frequency)}</span>
                  </div>
                  <div class="text-[11px] text-slate-500 font-mono mt-1">Afecta: ${esc(rec.affected_devices)}</div>
                  
                  <div class="mt-3 text-xs">
                    <span class="font-semibold text-slate-700">Causa Raíz:</span>
                    <p class="text-slate-600 text-[11px] mt-0.5">${esc(rec.root_cause)}</p>
                  </div>
                  <div class="mt-2 text-xs">
                    <span class="font-semibold text-emerald-700">Intervención registrada:</span>
                    <p class="text-slate-600 text-[11px] mt-0.5">${esc(rec.countermeasure)}</p>
                  </div>
                </div>
                <div class="mt-3 pt-2 border-t border-slate-200 text-right">
                  <span class="text-[11px] font-bold text-emerald-600">${esc(rec.status)}</span>
                </div>
              </div>
            `).join('')}
          </div>
        </div>
      </div>
    `;
  }

  function initEstadisticasCharts() {
    // 1. Chart Categorías en Estadísticas
    const ctxCat = document.getElementById('chart-categorias-stats');
    if (ctxCat && window.Chart) {
      const existingCat = window.Chart.getChart(ctxCat);
      if (existingCat) existingCat.destroy();

      const counts = db.categories.map(c => c.count);
      const labels = db.categories.map(c => c.name);
      new window.Chart(ctxCat, {
        type: 'doughnut',
        data: {
          labels: labels,
          datasets: [{
            data: counts,
            backgroundColor: [
              '#0284C7', '#0EA5E9', '#38BDF8', '#1E3A8A', '#3B82F6',
              '#6366F1', '#8B5CF6', '#10B981', '#F59E0B', '#F97316'
            ],
            borderWidth: 1.5,
            borderColor: '#FFFFFF'
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { position: 'right', labels: { boxWidth: 10, font: { size: 9 } } }
          },
          cutout: '65%'
        }
      });
    }

    // 2. Chart Mantenimientos en Estadísticas
    const ctxMant = document.getElementById('chart-mantenimientos-stats');
    if (ctxMant && window.Chart) {
      const existingMant = window.Chart.getChart(ctxMant);
      if (existingMant) existingMant.destroy();

      new window.Chart(ctxMant, {
        type: 'bar',
        data: {
          labels: (db.serverStats?.maintenance_months || []).map(month => month.label),
          datasets: [
            {
              label: 'Realizados',
              data: (db.serverStats?.maintenance_months || []).map(month => month.completed),
              backgroundColor: '#10B981',
              borderRadius: 4
            },
            {
              label: 'Programados pendientes',
              data: (db.serverStats?.maintenance_months || []).map(month => month.scheduled),
              backgroundColor: '#CBD5E1',
              borderRadius: 4
            }
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: {
            y: { beginAtZero: true, grid: { color: '#F1F5F9' } },
            x: { grid: { display: false } }
          },
          plugins: {
            legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 10 } } }
          }
        }
      });
    }
  }

  return { renderEstadisticasView, renderAnalisisView };
}
