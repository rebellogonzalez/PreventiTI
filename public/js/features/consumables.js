import { escapeHTML } from '../lib/html.js';

export function createConsumables({ db }) {
  function renderConsumiblesView(container) {
    const consumableAssets = db.assets.filter(a => a.has_consumable);

    container.innerHTML = `
      <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Control de Insumos y Consumibles Críticos</h1>
            <p class="text-sm text-slate-500 mt-0.5">Nivel de tóner y etiquetas en impresoras; vida útil estimada de filtros y baterías UPS (0–100%).</p>
          </div>
          <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-800">
            <b>Regla de Alerta Temprana:</b> Umbral de aviso preventivo configurado al <b>&le; 20%</b>.
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
          ${consumableAssets.map(a => {
            const isLow = a.consumable_level <= 20;
            const isCritical = a.consumable_level <= 5;
            const colorClass = isCritical ? 'bg-rose-500' : isLow ? 'bg-amber-500' : 'bg-emerald-500';
            const badgeClass = isCritical ? 'badge-critica' : isLow ? 'badge-atencion' : 'badge-normal';

            return `
              <div class="bg-white rounded-xl p-5 border ${isLow ? 'border-amber-300 shadow-amber-50' : 'border-slate-200'} shadow-sm flex flex-col justify-between">
                <div>
                  <div class="flex items-center justify-between">
                    <span class="font-mono text-xs font-bold text-slate-400">${escapeHTML(a.code)}</span>
                    <span class="badge-status ${badgeClass}">
                      <span class="dot"></span>
                      ${isCritical ? 'Crítico (&le;5%)' : isLow ? 'Atención (&le;20%)' : 'Óptimo'}
                    </span>
                  </div>
                  <h3 class="font-bold text-slate-900 text-sm mt-2 leading-snug">${escapeHTML(a.name)}</h3>
                  <div class="text-xs text-slate-500 mt-0.5">${escapeHTML(a.location_name)}</div>
                  
                  <div class="mt-4 p-3 bg-slate-50 rounded-lg border border-slate-100">
                    <div class="flex items-center justify-between text-xs mb-1">
                      <span class="font-semibold text-slate-700">${escapeHTML(a.consumable_type)}</span>
                      <span class="font-bold text-sm ${isLow ? 'text-amber-700' : 'text-slate-900'}">${a.consumable_level}%</span>
                    </div>
                    <div class="progress-bar-wrap h-2.5 bg-slate-200">
                      <div class="progress-bar-fill ${colorClass}" style="width: ${a.consumable_level}%;"></div>
                    </div>
                    <div class="flex justify-between text-[10px] text-slate-400 mt-1">
                      <span>0% (Agotado)</span>
                      <span>Umbral min: 20%</span>
                      <span>100%</span>
                    </div>
                  </div>
                </div>

                <div class="mt-5 pt-3 border-t border-slate-100 flex items-center justify-between">
                  <span class="text-xs text-slate-400">Resp: ${escapeHTML(a.responsible.split(' ')[0])}</span>
                  <button onclick="window.openModalConsumable(${a.id})" class="px-3 py-1.5 bg-sky-600 hover:bg-sky-700 text-white rounded-lg text-xs font-semibold shadow-sm transition">
                    Reponer / Calibrar
                  </button>
                </div>
              </div>
            `;
          }).join('')}
        </div>
      </div>
    `;
  }

  return { renderConsumiblesView };
}
