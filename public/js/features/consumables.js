import { escapeHTML } from '../lib/html.js';

const states = {
  critica: { label: 'Crítico (≤ 5 %)', badge: 'badge-critica', bar: 'bg-rose-500' },
  atencion: { label: 'Atención (> 5 % y ≤ 20 %)', badge: 'badge-atencion', bar: 'bg-amber-500' },
  optimo: { label: 'Óptimo (> 20 %)', badge: 'badge-normal', bar: 'bg-emerald-500' },
  sinLectura: { label: 'Sin lectura', badge: 'badge-neutral' },
};

function stateFor(level) {
  if (level == null || level === '' || !Number.isFinite(Number(level))) return 'sinLectura';
  if (level <= 5) return 'critica';
  if (level <= 20) return 'atencion';
  return 'optimo';
}

function deviceType(asset) {
  return String(asset.category_id ?? 'sin-categoria');
}

function renderCard(asset) {
  const state = stateFor(asset.consumable_level);
  const { label, badge, bar } = states[state];
  const level = state === 'sinLectura' ? null : Number(asset.consumable_level);
  const isLow = state === 'critica' || state === 'atencion';
  return `
    <article class="min-w-0 bg-white rounded-xl p-5 border ${isLow ? 'border-amber-300 shadow-amber-50' : 'border-slate-200'} shadow-sm flex flex-col justify-between">
      <div>
        <div class="flex flex-wrap items-center justify-between gap-2">
          <span class="font-mono text-xs font-bold text-slate-400">${escapeHTML(asset.code)}</span>
          <span class="badge-status ${badge}"><span class="dot" aria-hidden="true"></span>${label}</span>
        </div>
        <h3 class="font-bold text-slate-900 text-sm mt-2 leading-snug">${escapeHTML(asset.name)}</h3>
        <div class="text-xs text-slate-500 mt-0.5">${escapeHTML(asset.location_name)}</div>
        <div class="mt-4 p-3 bg-slate-50 rounded-lg border border-slate-100">
          <div class="flex flex-wrap items-center justify-between gap-2 text-xs mb-1">
            <span class="font-semibold text-slate-700">${escapeHTML(asset.consumable_type || 'Insumo')}</span>
            <span class="font-bold text-sm ${isLow ? 'text-amber-700' : 'text-slate-900'}">${level === null ? '—' : level + '%'}</span>
          </div>
          ${level !== null ? `<div class="progress-bar-wrap h-2.5 bg-slate-200">
            <div class="progress-bar-fill ${bar}" style="width: ${level}%"></div>
          </div>
          <div class="flex justify-between text-[10px] text-slate-400 mt-1">
            <span>0% (Agotado)</span><span>Umbral mín. 20%</span><span>100%</span>
          </div>` : ''}
        </div>
      </div>
      <div class="mt-5 pt-3 border-t border-slate-100 flex flex-wrap items-center justify-between gap-2">
        <span class="text-xs text-slate-400">Resp.: ${escapeHTML(asset.responsible || 'Sin asignar')}</span>
        <button type="button" onclick="window.openModalConsumable(${Number(asset.id)})" class="px-3 py-1.5 bg-sky-600 hover:bg-sky-700 text-white rounded-lg text-xs font-semibold shadow-sm transition">
          Reponer / Calibrar
        </button>
      </div>
    </article>`;
}

export function createConsumables({ db }) {
  let selectedState = '';
  let selectedType = '';

  function renderConsumiblesView(container) {
    const assets = db.assets.filter(asset => asset.has_consumable);
    const types = new Map(assets.map(asset => [deviceType(asset), asset.category_name || 'Sin categoría']));
    if (!types.has(selectedType)) selectedType = '';

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
        <section aria-label="Filtros de insumos" class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm space-y-3">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <label class="min-w-0 text-xs font-semibold text-slate-700">Estado del insumo
              <select id="consumable-state" aria-controls="consumable-results" class="mt-1 w-full py-2 px-3 border border-slate-300 rounded-lg bg-white text-base sm:text-sm font-normal">
                <option value="">Todos los estados</option>
                ${Object.entries(states).map(([key, state]) => `<option value="${key}">${state.label}</option>`).join('')}
              </select>
            </label>
            <label class="min-w-0 text-xs font-semibold text-slate-700">Tipo de dispositivo
              <select id="consumable-device-type" aria-controls="consumable-results" class="mt-1 w-full py-2 px-3 border border-slate-300 rounded-lg bg-white text-base sm:text-sm font-normal">
                <option value="">Todos los tipos</option>
                ${[...types].sort((a, b) => a[1].localeCompare(b[1], 'es')).map(([id, name]) =>
                  `<option value="${escapeHTML(id)}">${escapeHTML(name)}</option>`).join('')}
              </select>
            </label>
          </div>
          <div class="flex flex-wrap items-center justify-between gap-2 text-xs">
            <span id="consumable-count" role="status" class="text-slate-500"></span>
            <button type="button" id="consumable-clear" class="text-sky-700 font-semibold disabled:text-slate-400">Limpiar filtros</button>
          </div>
        </section>
        <div id="consumable-results" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5"></div>
      </div>`;

    const stateSelect = container.querySelector('#consumable-state');
    const typeSelect = container.querySelector('#consumable-device-type');
    const clear = container.querySelector('#consumable-clear');
    stateSelect.value = selectedState;
    typeSelect.value = selectedType;

    function updateResults() {
      selectedState = stateSelect.value;
      selectedType = typeSelect.value;
      const visible = assets.filter(asset =>
        (!selectedState || stateFor(asset.consumable_level) === selectedState) &&
        (!selectedType || deviceType(asset) === selectedType));
      container.querySelector('#consumable-count').textContent = `${visible.length} de ${assets.length} dispositivos`;
      clear.disabled = !selectedState && !selectedType;
      container.querySelector('#consumable-results').innerHTML = visible.length
        ? visible.map(renderCard).join('')
        : `<p class="col-span-full p-5 text-sm text-slate-600">${assets.length
          ? 'Ningún dispositivo coincide con los filtros seleccionados.'
          : 'No hay dispositivos con insumos en el inventario.'}</p>`;
    }

    stateSelect.addEventListener('change', updateResults);
    typeSelect.addEventListener('change', updateResults);
    clear.addEventListener('click', () => {
      stateSelect.value = '';
      typeSelect.value = '';
      updateResults();
      stateSelect.focus();
    });
    updateResults();
  }

  return { renderConsumiblesView };
}
