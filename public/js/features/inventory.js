export function createInventory({ db, getCurrentUser, getRoute, navigateTo }) {
  let invSearch = '';
  let invCategory = '';
  let invLocation = '';
  let invAlert = '';
  let invPage = 1;
  const invPerPage = 12;

  window.filterInventarioByAlert = function (alertType) {
    // Dashboard shortcuts start a fresh search so their totals match the results.
    invSearch = '';
    invCategory = '';
    invLocation = '';
    invAlert = alertType === 'todos' ? '' : alertType;
    invPage = 1;
    if (getRoute() === 'inventario') renderInventarioView(document.getElementById('view-container'));
    else navigateTo('inventario');
  };

  function renderInventarioView(container) {
    const helpers = window.PreventiTIInventory;
    const esc = helpers.escapeHTML;
    const user = getCurrentUser();
    const canManageAssets = user.role === 'administrador' || db.currentUserRole === 'administrador';
    const inventoryBadge = document.getElementById('badge-nav-inventario');
    if (inventoryBadge) inventoryBadge.textContent = db.assets.length;

    container.innerHTML = `
      <div class="space-y-5">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Inventario de dispositivos</h1>
            <p class="text-sm text-slate-500 mt-0.5">Consulta los dispositivos registrados, su ubicación y su estado actual.</p>
          </div>
          ${canManageAssets ? `
            <button onclick="window.openCreateAsset()" class="inline-flex items-center gap-2 px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white rounded-lg text-xs font-semibold shadow-sm transition">
              <i data-lucide="plus" class="w-4 h-4"></i> Alta de Nuevo Activo
            </button>
          ` : ''}
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm space-y-3">
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="sm:col-span-2 lg:col-span-4">
              <label for="input-search-assets" class="block text-xs font-semibold text-slate-700 mb-1.5">Buscar dispositivos</label>
              <div class="relative">
                <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" aria-hidden="true"></i>
                <input type="search" id="input-search-assets" value="${esc(invSearch)}" placeholder="Nombre, código, serie, responsable, categoría o ubicación…" autocomplete="off" aria-controls="inventory-results"
                       class="w-full pl-9 pr-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100">
              </div>
            </div>
            <div class="lg:col-span-2">
              <label for="select-filter-alert" class="block text-xs font-semibold text-slate-700 mb-1.5">Estado</label>
              <select id="select-filter-alert" aria-controls="inventory-results" class="w-full py-2 px-3 text-xs border border-slate-300 rounded-lg focus:outline-none focus:border-sky-500 bg-white">
                <option value="">Todos los estados</option>
                ${Object.entries(helpers.statuses).map(([value, label]) => `<option value="${value}" ${invAlert === value ? 'selected' : ''}>${label}</option>`).join('')}
              </select>
            </div>
            <div>
              <label for="select-filter-category" class="block text-xs font-semibold text-slate-700 mb-1.5">Categoría</label>
              <select id="select-filter-category" aria-controls="inventory-results" class="w-full py-2 px-3 text-xs border border-slate-300 rounded-lg focus:outline-none focus:border-sky-500 bg-white">
                <option value="">Todas las categorías</option>
                ${db.categories.map(c => `<option value="${esc(c.id)}" ${invCategory == c.id ? 'selected' : ''}>${esc(c.name)}</option>`).join('')}
              </select>
            </div>
            <div>
              <label for="select-filter-location" class="block text-xs font-semibold text-slate-700 mb-1.5">Ubicación</label>
              <select id="select-filter-location" aria-controls="inventory-results" class="w-full py-2 px-3 text-xs border border-slate-300 rounded-lg focus:outline-none focus:border-sky-500 bg-white">
                <option value="">Todas las ubicaciones</option>
                ${db.locations.map(l => `<option value="${esc(l.id)}" ${invLocation == l.id ? 'selected' : ''}>${esc(l.name)}</option>`).join('')}
              </select>
            </div>
          </div>
          <div class="flex flex-wrap gap-2 items-center justify-between text-xs text-slate-500 pt-3 border-t border-slate-100">
            <span id="inventory-count" role="status" aria-live="polite" aria-atomic="true"></span>
            <button id="inventory-clear-filters" type="button" onclick="window.clearAssetFilters()" class="text-sky-600 hover:text-sky-800 font-semibold">Limpiar búsqueda y filtros &times;</button>
          </div>
        </div>
        <div id="inventory-results"></div>
      </div>
    `;

    // Update only results, keeping the input, cursor and filter focus intact.
    container.querySelector('#input-search-assets').addEventListener('input', event => {
      invSearch = event.target.value;
      invPage = 1;
      renderInventoryResults(container);
    });
    [
      ['select-filter-category', value => { invCategory = value; }],
      ['select-filter-location', value => { invLocation = value; }],
      ['select-filter-alert', value => { invAlert = value; }],
    ].forEach(([id, setFilter]) => {
      container.querySelector(`#${id}`).addEventListener('change', event => {
        setFilter(event.target.value);
        invPage = 1;
        renderInventoryResults(container);
      });
    });
    renderInventoryResults(container);
  }

  function renderInventoryResults(container) {
    const helpers = window.PreventiTIInventory;
    const esc = helpers.escapeHTML;
    const user = getCurrentUser();
    const canManageAssets = user.role === 'administrador' || db.currentUserRole === 'administrador';
    const filtered = helpers.selectAssets(db.assets, { query: invSearch, category: invCategory, location: invLocation, status: invAlert });
    const totalPages = Math.max(1, Math.ceil(filtered.length / invPerPage));
    invPage = Math.max(1, Math.min(invPage, totalPages));
    const startIdx = (invPage - 1) * invPerPage;
    const paginated = filtered.slice(startIdx, startIdx + invPerPage);
    container.querySelector('#inventory-count').textContent = `${filtered.length} de ${db.assets.length} dispositivos${filtered.length ? ` · Mostrando ${startIdx + 1}–${startIdx + paginated.length}` : ''}`;
    container.querySelector('#inventory-clear-filters').hidden = !(invSearch || invCategory || invLocation || invAlert);
    container.querySelector('#inventory-results').innerHTML = `
        <!-- Tabla -->
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
          <div class="overflow-x-auto">
            <table class="min-w-[1100px] w-full text-left text-xs text-slate-600">
              <thead class="bg-slate-50 text-slate-700 font-semibold border-b border-slate-200">
                <tr>
                  <th class="py-3 px-4">Código / Serie</th>
                  <th class="py-3 px-4">Equipo / Nombre</th>
                  <th class="py-3 px-4">Categoría</th>
                  <th class="py-3 px-4">Ubicación</th>
                  <th class="py-3 px-4">Responsable</th>
                  <th class="py-3 px-4">Próx. Service</th>
                  <th class="py-3 px-4">Consumible</th>
                  <th class="py-3 px-4">Estado</th>
                  <th class="py-3 px-4 text-right">Acciones</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                ${paginated.length === 0 ? `
                  <tr>
                    <td colspan="9" class="py-12 text-center text-slate-400">
                      <i data-lucide="search-x" class="w-8 h-8 mx-auto mb-2 text-slate-300"></i>
                      ${db.assets.length ? 'No se encontraron dispositivos. Prueba otra búsqueda o limpia los filtros.' : 'Todavía no hay dispositivos registrados.'}
                    </td>
                  </tr>
                ` : paginated.map(a => {
                  const status = helpers.statusFor(a);
                  const retired = helpers.isRetired(a);
                  return `
                    <tr data-asset-id="${a.id}" class="hover:bg-slate-50/80 transition ${!a.active ? 'opacity-60 bg-slate-50' : ''}">
                      <td class="py-3 px-4 font-mono font-semibold text-slate-900 whitespace-nowrap">
                        <div>${esc(a.code)}</div>
                        <div class="text-[10px] text-slate-400">${esc(a.serial)}</div>
                      </td>
                      <td class="py-3 px-4 font-medium text-slate-800">
                        <div class="truncate max-w-[200px]" title="${esc(a.name)}">${esc(a.name)}</div>
                        ${retired ? '<span class="text-[10px] font-bold text-rose-600 uppercase">Baja lógica</span>' : !a.active ? `<span class="text-[10px] font-bold text-rose-600 uppercase">${esc(a.state)}</span>` : ''}
                      </td>
                      <td class="py-3 px-4">
                        <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-700 text-[11px] font-medium">${esc(a.category_name)}</span>
                      </td>
                      <td class="py-3 px-4 text-slate-600 truncate max-w-[140px]" title="${esc(a.location_name)}">
                        ${esc(a.location_name)}
                      </td>
                      <td class="py-3 px-4 text-slate-600">${esc(a.responsible)}</td>
                      <td class="py-3 px-4">
                        <div class="font-medium ${a.days_to_maintenance != null && a.days_to_maintenance <= 7 ? 'text-amber-700 font-bold' : 'text-slate-700'}">
                          ${esc(a.next_maintenance || 'Sin fecha registrada')}
                        </div>
                        <div class="text-[10px] text-slate-400">
                          ${a.days_to_maintenance == null ? 'Sin programación' : a.days_to_maintenance < 0 ? `<span class="text-rose-600 font-bold">Vencido (${Math.abs(a.days_to_maintenance)}d)</span>` : `Faltan ${a.days_to_maintenance}d`}
                        </div>
                      </td>
                      <td class="py-3 px-4">
                        ${a.has_consumable && a.consumable_level != null ? `
                          <div>
                            <div class="flex items-center justify-between text-[11px] mb-1">
                              <span class="font-bold ${a.consumable_level <= 20 ? 'text-amber-700' : 'text-slate-700'}">${a.consumable_level}%</span>
                            </div>
                            <div class="progress-bar-wrap w-16">
                              <div class="progress-bar-fill ${a.consumable_level <= 20 ? 'bg-amber-500' : 'bg-emerald-500'}" style="width: ${a.consumable_level}%;"></div>
                            </div>
                          </div>
                        ` : `<span class="text-slate-300">—</span>`}
                      </td>
                      <td class="py-3 px-4">
                        <span class="badge-status badge-${status} whitespace-nowrap">
                          <span class="dot" aria-hidden="true"></span>
                          ${helpers.statuses[status]}
                        </span>
                      </td>
                      <td class="py-3 px-4 text-right">
                        <div class="flex items-center justify-end gap-1.5">
                          <button onclick="window.viewAssetDetail(${a.id})" title="Ficha Técnica" class="p-1 text-slate-500 hover:text-sky-600 hover:bg-slate-100 rounded">
                            <i data-lucide="eye" class="w-4 h-4"></i>
                          </button>
                          <button onclick="window.openCreateReportForAsset(${a.id})" title="Reportar Falla" class="p-1 text-slate-500 hover:text-amber-600 hover:bg-slate-100 rounded">
                            <i data-lucide="alert-circle" class="w-4 h-4"></i>
                          </button>
                          ${canManageAssets ? `
                            <button onclick="window.openEditAsset(${a.id})" title="Editar activo" aria-label="Editar ${esc(a.code)}" class="p-1 text-slate-500 hover:text-sky-600 hover:bg-slate-100 rounded">
                              <i data-lucide="pencil" class="w-4 h-4"></i>
                            </button>
                            <button onclick="window.toggleAssetActive(${a.id})" title="${retired ? 'Revertir baja y recuperar estado anterior' : 'Dar de baja lógica'}" class="p-1 ${retired ? 'text-rose-600 hover:text-emerald-600' : 'text-slate-400 hover:text-rose-600'} hover:bg-slate-100 rounded">
                              <i data-lucide="${retired ? 'rotate-ccw' : 'trash-2'}" class="w-4 h-4"></i>
                            </button>
                          ` : ''}
                        </div>
                      </td>
                    </tr>
                  `;
                }).join('')}
              </tbody>
            </table>
          </div>

          <!-- Paginador -->
          <div class="p-4 border-t border-slate-200 flex flex-wrap gap-3 items-center justify-between text-xs">
            <span class="text-slate-500">Página ${invPage} de ${totalPages}</span>
            <div class="flex flex-wrap items-center gap-1" role="group" aria-label="Páginas del inventario">
              <button onclick="window.changeInvPage(${invPage - 1})" ${invPage === 1 ? 'disabled class="px-2 py-1 border rounded text-slate-300 cursor-not-allowed"' : 'class="px-2 py-1 border rounded text-slate-700 hover:bg-slate-100"'}>
                &larr; Anterior
              </button>
              ${Array.from({ length: totalPages }, (_, i) => i + 1).map(p => `
                <button onclick="window.changeInvPage(${p})" ${p === invPage ? 'aria-current="page"' : ''} class="px-2.5 py-1 border rounded ${p === invPage ? 'bg-sky-600 text-white font-semibold border-sky-600' : 'text-slate-700 hover:bg-slate-100'}">
                  ${p}
                </button>
              `).join('')}
              <button onclick="window.changeInvPage(${invPage + 1})" ${invPage === totalPages ? 'disabled class="px-2 py-1 border rounded text-slate-300 cursor-not-allowed"' : 'class="px-2 py-1 border rounded text-slate-700 hover:bg-slate-100"'}>
                Siguiente &rarr;
              </button>
            </div>
          </div>
        </div>
    `;
    if (window.lucide) window.lucide.createIcons();
  }

  window.changeInvPage = function (p) {
    invPage = p;
    renderInventoryResults(document.getElementById('view-container'));
  };

  window.clearAssetFilters = function () {
    invSearch = '';
    invCategory = '';
    invLocation = '';
    invAlert = '';
    invPage = 1;
    const container = document.getElementById('view-container');
    renderInventarioView(container);
    container.querySelector('#input-search-assets').focus();
  };

  return { renderInventarioView };
}
