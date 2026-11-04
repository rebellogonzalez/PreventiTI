import { escapeHTML } from '../lib/html.js';
import { selectMaintenanceHistory } from '../lib/maintenance-history.js';

export function createMaintenance({ db }) {
  let historySearch = '';

  function renderMantenimientosView(container) {
    container.innerHTML = `
      <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Mantenimiento Preventivo y Calendarización</h1>
            <p class="text-sm text-slate-500 mt-0.5">Automatización de inspecciones técnicas mediante cálculo determinista por categoría (30 a 180 días).</p>
          </div>
          <button data-permission="can_maintain_assets" onclick="window.openModal('modal-registrar-mantenimiento')" class="inline-flex items-center gap-2 px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-lg text-xs font-semibold shadow-sm transition">
            <i data-lucide="plus-circle" class="w-4 h-4"></i> Registrar Inspección
          </button>
        </div>

        <!-- Explicación Visual de la Regla Determinista -->
        <div class="bg-gradient-to-r from-sky-900 to-slate-900 rounded-xl p-5 text-white shadow-sm border border-slate-800">
          <div class="flex items-start gap-4">
            <div class="p-3 bg-sky-500/20 text-sky-300 rounded-xl border border-sky-400/30">
              <i data-lucide="cpu" class="w-6 h-6"></i>
            </div>
            <div class="flex-1">
              <h3 class="text-base font-bold text-white">Algoritmo Determinista de Proyección de Service</h3>
              <p class="text-xs text-slate-300 mt-1">
                La fecha de próxima intervención es calculada estrictamente en la capa de negocio:
                <code class="bg-black/30 px-2 py-0.5 rounded text-sky-300 font-mono text-xs">proxima_fecha = fecha_mantenimiento + periodicidad_dias</code>.
                Esto elimina cálculos subjetivos y garantiza una planificación matemática inmutable.
              </p>
              <div class="mt-3 grid grid-cols-2 sm:grid-cols-5 gap-2 text-xs">
                <div class="bg-white/10 p-2 rounded"><b>30 días:</b> Tótems & Servidores</div>
                <div class="bg-white/10 p-2 rounded"><b>60 días:</b> Impresoras & Clima</div>
                <div class="bg-white/10 p-2 rounded"><b>90 días:</b> POS, CCTV & UPS</div>
                <div class="bg-white/10 p-2 rounded"><b>120 días:</b> Cartelería Digital</div>
                <div class="bg-white/10 p-2 rounded"><b>180 días:</b> Laptops & Red</div>
              </div>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
          <div class="maintenance-history-heading p-4 border-b border-slate-100">
            <h3 id="maintenance-history-title" class="font-bold text-slate-900 text-sm">Historial de Revisiones Técnicas y Futuras Inspecciones</h3>
            <span id="maintenance-history-count" role="status" aria-live="polite" aria-atomic="true" class="text-xs text-slate-400"></span>
          </div>
          <div class="maintenance-history-search">
            <label for="maintenance-history-search">Buscar revisiones e inspecciones</label>
            <div class="maintenance-history-search-controls">
              <input id="maintenance-history-search" type="search" value="${escapeHTML(historySearch)}" placeholder="Dispositivo, código, categoría, ubicación, técnico u observaciones…" autocomplete="off" aria-controls="maintenance-history-results" aria-describedby="maintenance-history-search-help">
              <button id="maintenance-history-clear" type="button">Limpiar búsqueda</button>
            </div>
            <p id="maintenance-history-search-help">Puedes combinar palabras y buscar fechas de ejecución o próximas inspecciones: AAAA-MM-DD o DD/MM/AAAA.</p>
          </div>
          <div class="overflow-x-auto">
            <table aria-labelledby="maintenance-history-title" class="w-full text-left text-xs text-slate-600">
              <thead class="bg-slate-50 text-slate-700 font-semibold border-b border-slate-200">
                <tr>
                  <th class="py-3 px-4">Activo</th>
                  <th class="py-3 px-4">Técnico Interviniente</th>
                  <th class="py-3 px-4">Fecha Ejecución</th>
                  <th class="py-3 px-4">Periodicidad</th>
                  <th class="py-3 px-4">Próxima Fecha Proyectada</th>
                  <th class="py-3 px-4">Detalle Técnico de la Intervención</th>
                </tr>
              </thead>
              <tbody id="maintenance-history-results" class="divide-y divide-slate-100"></tbody>
            </table>
          </div>
        </div>
      </div>
    `;
    const searchInput = container.querySelector('#maintenance-history-search');
    searchInput.addEventListener('input', event => {
      historySearch = event.target.value;
      renderHistoryResults(container);
    });
    container.querySelector('#maintenance-history-clear').addEventListener('click', () => {
      historySearch = '';
      searchInput.value = '';
      renderHistoryResults(container);
      searchInput.focus();
    });
    renderHistoryResults(container);
  }

  function renderHistoryResults(container) {
    const maintenances = db.maintenances || [];
    const visible = selectMaintenanceHistory(maintenances, historySearch, db.assets || []);
    container.querySelector('#maintenance-history-count').textContent = `${visible.length} de ${maintenances.length} registros auditables`;
    container.querySelector('#maintenance-history-clear').hidden = historySearch.length === 0;
    container.querySelector('#maintenance-history-results').innerHTML = visible.length
      ? visible.map(m => `
                  <tr class="hover:bg-slate-50/80 transition">
                    <td class="py-3 px-4">
                      <div class="font-semibold text-slate-900">${escapeHTML(m.asset_name)}</div>
                      <div class="font-mono text-[11px] text-slate-400">${escapeHTML(m.asset_code)}</div>
                      ${m.asset_category || m.location_name ? `<div class="maintenance-history-asset-context">${escapeHTML([m.asset_category, m.location_name].filter(Boolean).join(' · '))}</div>` : ''}
                    </td>
                    <td class="py-3 px-4 font-medium text-slate-800">${escapeHTML(m.technician_name || 'No registrado')}</td>
                    <td class="py-3 px-4 font-mono">${escapeHTML(m.maintenance_date)}</td>
                    <td class="py-3 px-4">
                      <span class="px-2 py-0.5 rounded bg-slate-100 font-semibold text-slate-700">${m.periodicity_days != null ? `${escapeHTML(m.periodicity_days)} días` : 'No registrada'}</span>
                    </td>
                    <td class="py-3 px-4 font-mono font-bold text-emerald-700">
                      ${escapeHTML(m.next_date)}
                    </td>
                    <td class="py-3 px-4 text-slate-600 max-w-xs">
                      <div class="truncate" title="${escapeHTML(m.detail)}">${escapeHTML(m.detail)}</div>
                      ${m.observations ? `<div class="maintenance-history-observations">Observaciones: ${escapeHTML(m.observations)}</div>` : ''}
                    </td>
                  </tr>
                `).join('')
      : `<tr><td colspan="6" class="maintenance-history-empty">${maintenances.length
        ? 'No se encontraron revisiones o inspecciones para esta búsqueda. Prueba con otras palabras o limpia el buscador.'
        : 'Todavía no hay revisiones técnicas registradas.'}</td></tr>`;
  }

  return { renderMantenimientosView };
}
