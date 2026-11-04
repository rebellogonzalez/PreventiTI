import { escapeHTML as reportDisplayText } from '../lib/html.js';
import { enhanceAssetSelect } from '../ui/asset-select.js';

export function createReports({ db, getCurrentUser, saveDB }) {
  let reportFilterPriority = '';
  let reportFilterTechnician = '';
  let reportViewMode = 'kanban';
  let reportHistoryOpen = false;
  let reportHistoryFiltersOpen = false;
  let reportHistoryFilters = { query: '', status: '', asset: '', technician: '', from: '', to: '', sort: 'newest' };
  let reportHistoryPage = 1;
  const reportHistoryPageSize = 20;

  // Presentation only: these keys match the existing report statuses.
  const reportStatusPresentation = [
    { key: 'Recibido/Derivado', label: 'Recibido / Derivado', tone: 'received' },
    { key: 'En curso', label: 'En curso', tone: 'progress' },
    { key: 'Diagnóstico', label: 'Diagnóstico', tone: 'diagnosis' },
    { key: 'Finalizado', label: 'Finalizado', tone: 'finished' },
  ];

  function reportPriorityBadge(priority) {
    const tone = { Critica: 'critical', 'Crítica': 'critical', Alta: 'high', Media: 'medium', Baja: 'low' }[priority] || 'low';
    return `<span class="report-priority report-priority--${tone}">${reportDisplayText(priority === 'Critica' ? 'Crítica' : priority)}</span>`;
  }

  function reportActionButtons(report, canManageReports) {
    return `
      ${canManageReports && report.status !== 'Finalizado' ? `<button type="button" onclick="window.manageReport(${Number(report.id)})" class="report-button report-button--primary" aria-label="Gestionar ${reportDisplayText(report.code)}">Gestionar</button>` : ''}
      <button type="button" onclick="window.viewReportDetail(${Number(report.id)})" class="report-button report-button--secondary" aria-label="Ver detalle de ${reportDisplayText(report.code)}">Ver</button>
    `;
  }

  function renderReportCard(report, canManageReports) {
    const technician = report.assigned_technician;
    return `
      <article class="report-card" aria-label="Reporte ${reportDisplayText(report.code)}">
        <div class="report-card-top">
          <span class="report-code">${reportDisplayText(report.code)}</span>
          ${reportPriorityBadge(report.priority)}
        </div>
        <h3 class="report-card-title">${reportDisplayText(report.asset_name)}</h3>
        <div class="report-asset-code">${reportDisplayText(report.asset_code)}</div>
        <p class="report-card-description" title="${reportDisplayText(report.description)}">${reportDisplayText(report.description)}</p>
        <div class="report-card-meta">
          <span class="report-technician" title="${reportDisplayText(technician ? `${technician.name}${technician.specialty ? ` — ${technician.specialty}` : ''}` : 'Sin asignar')}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="12" cy="8" r="3.5"/><path d="M5 21v-2a7 7 0 0 1 14 0v2"/></svg>
            <span>${reportDisplayText(technician?.name || 'Sin asignar')}</span>
          </span>
          <span class="report-date" title="${reportDisplayText(report.created_at)}">${reportDisplayText((report.created_at || '').split(' ')[0])}</span>
        </div>
        <div class="report-card-actions">${reportActionButtons(report, canManageReports)}</div>
      </article>
    `;
  }

  function renderReportsView(container) {
    if (reportHistoryOpen) {
      renderReportHistoryView(container);
      return;
    }
    const currentUser = getCurrentUser();
    const canManageReports = !!window.currentUserProfile.permissions.can_manage_reports;
    const allReports = db.reports || [];
    const technicians = [...new Map(allReports.filter(report => report.assigned_technician).map(report => [
      String(report.assigned_technician.id), report.assigned_technician,
    ])).values()].sort((a, b) => a.name.localeCompare(b.name, 'es'));
    const technicianFilter = reportFilterTechnician === 'unassigned' || technicians.some(technician => String(technician.id) === reportFilterTechnician)
      ? reportFilterTechnician : '';
    const reports = allReports.filter(report =>
      (reportFilterPriority === '' || report.priority === reportFilterPriority) &&
      (technicianFilter === '' || (technicianFilter === 'unassigned'
        ? !report.assigned_technician
        : String(report.assigned_technician?.id) === technicianFilter))
    );
    const columns = [...reportStatusPresentation];
    // Keep any historical, unrecognized value visible without changing it.
    reports.forEach(report => {
      const status = report.status || 'Recibido/Derivado';
      if (!columns.some(column => column.key === status)) {
        columns.push({ key: status, label: status, tone: 'neutral' });
      }
    });

    container.innerHTML = `
      <div class="reports-view">
        <div class="reports-heading">
          <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Reportes de situación</h1>
            <p class="text-sm text-slate-500 mt-1">Seguimiento de situaciones sobre los activos, organizadas por estado.</p>
          </div>
          <div class="reports-heading-actions">
            <button type="button" id="open-report-history" class="report-button report-button--secondary report-create">Historial</button>
            <div class="report-view-switch" role="group" aria-label="Vista de reportes">
              <button type="button" data-report-view="kanban" aria-pressed="${reportViewMode === 'kanban'}" aria-controls="report-results">Kanban</button>
              <button type="button" data-report-view="list" aria-pressed="${reportViewMode === 'list'}" aria-controls="report-results">Lista</button>
            </div>
            <button type="button" onclick="window.openModal('modal-nuevo-reporte')" class="report-button report-button--primary report-create">Crear reporte</button>
          </div>
        </div>

        <div class="report-filters">
          <span class="font-semibold text-slate-600">Filtrar por:</span>
          <label class="sr-only" for="reporte-prioridad-filtro">Prioridad</label>
          <select id="reporte-prioridad-filtro">
            <option value="">Todas las prioridades</option>
            <option value="Baja" ${reportFilterPriority === 'Baja' ? 'selected' : ''}>Baja</option>
            <option value="Media" ${reportFilterPriority === 'Media' ? 'selected' : ''}>Media</option>
            <option value="Alta" ${reportFilterPriority === 'Alta' ? 'selected' : ''}>Alta</option>
            <option value="Critica" ${reportFilterPriority === 'Critica' ? 'selected' : ''}>Crítica</option>
          </select>
          <label class="sr-only" for="reporte-tecnico-filtro">Técnico activo</label>
          <select id="reporte-tecnico-filtro">
            <option value="">Todos los técnicos</option>
            <option value="unassigned" ${technicianFilter === 'unassigned' ? 'selected' : ''}>Sin asignar</option>
            ${technicians.map(technician => `<option value="${reportDisplayText(technician.id)}" ${technicianFilter === String(technician.id) ? 'selected' : ''}>${reportDisplayText(technician.name)}</option>`).join('')}
          </select>
          <span class="report-total" role="status">${reports.length} ${reports.length === 1 ? 'reporte' : 'reportes'}</span>
        </div>

        <div id="report-results">
        ${reportViewMode === 'kanban' ? `
          <div class="report-board">
            ${columns.map((column, index) => {
              const columnReports = reports.filter(report => (report.status || 'Recibido/Derivado') === column.key);
              return `
                <section class="report-column report-tone--${column.tone}" aria-labelledby="report-column-${index}">
                  <div class="report-column-heading">
                    <h2 id="report-column-${index}"><span class="report-state-dot" aria-hidden="true"></span>${reportDisplayText(column.label)}</h2>
                    <span class="report-column-count" aria-label="${columnReports.length} reportes">${columnReports.length}</span>
                  </div>
                  <div class="report-column-cards" tabindex="0" role="region" aria-label="Reportes: ${reportDisplayText(column.label)}">
                    ${columnReports.length ? columnReports.map(report => renderReportCard(report, canManageReports)).join('') : `
                      <div class="report-column-empty">${reportFilterPriority || technicianFilter ? 'Sin reportes para estos filtros.' : 'No hay reportes en este estado.'}</div>
                    `}
                  </div>
                </section>
              `;
            }).join('')}
          </div>
        ` : `
        <div class="report-list bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-600">
              <thead class="bg-slate-50 text-slate-700 font-semibold border-b border-slate-200">
                <tr>
                  <th class="py-3 px-4">Reporte</th>
                  <th class="py-3 px-4">Activo</th>
                  <th class="py-3 px-4">Prioridad</th>
                  <th class="py-3 px-4">Estado</th>
                  <th class="py-3 px-4">Técnico activo</th>
                  <th class="py-3 px-4">Informado por</th>
                  <th class="py-3 px-4">Fecha</th>
                  <th class="py-3 px-4">Descripción</th>
                  <th class="py-3 px-4 text-right">Acción</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                ${reports.length === 0 ? `
                  <tr><td colspan="9" class="py-10 px-4 text-center text-slate-400">No hay reportes para el filtro seleccionado.</td></tr>
                ` : reports.map(report => `
                  <tr class="hover:bg-slate-50/80 transition">
                    <td class="py-3 px-4 font-mono font-bold text-slate-900">${reportDisplayText(report.code)}</td>
                    <td class="py-3 px-4">
                      <div class="font-semibold text-slate-800">${reportDisplayText(report.asset_name)}</div>
                      <div class="text-[11px] text-slate-400 font-mono">${reportDisplayText(report.asset_code)}</div>
                    </td>
                    <td class="py-3 px-4">
                      ${reportPriorityBadge(report.priority)}
                    </td>
                    <td class="py-3 px-4"><span class="report-state-badge report-tone--${columns.find(column => column.key === (report.status || 'Recibido/Derivado')).tone}"><span class="report-state-dot" aria-hidden="true"></span>${reportDisplayText(report.status || 'Recibido/Derivado')}</span></td>
                    <td class="py-3 px-4">
                      ${report.assigned_technician ? `<div class="font-medium text-slate-800">${reportDisplayText(report.assigned_technician.name)}</div><div class="text-[10px] text-slate-400">${reportDisplayText(report.assigned_technician.specialty)}</div>` : '<span class="text-slate-400">Sin asignar</span>'}
                    </td>
                    <td class="py-3 px-4">
                      <div class="font-medium text-slate-800">${reportDisplayText(report.creator_name)}</div>
                      <div class="text-[10px] text-slate-400">${reportDisplayText(report.creator_role)}</div>
                    </td>
                    <td class="py-3 px-4 font-mono text-[11px] text-slate-500">${reportDisplayText(report.created_at)}</td>
                    <td class="py-3 px-4 max-w-sm truncate" title="${reportDisplayText(report.description)}">${reportDisplayText(report.description)}</td>
                    <td class="py-3 px-4 text-right">
                      <div class="report-list-actions">${reportActionButtons(report, canManageReports)}</div>
                    </td>
                  </tr>
                `).join('')}
              </tbody>
            </table>
          </div>
        </div>
        `}
        </div>
      </div>
    `;

    container.querySelector('#open-report-history').addEventListener('click', () => {
      reportHistoryOpen = true;
      renderReportsView(container);
      container.querySelector('#report-history-title').focus();
    });
    const priorityFilter = document.getElementById('reporte-prioridad-filtro');
    if (priorityFilter) {
      priorityFilter.addEventListener('change', event => {
        reportFilterPriority = event.target.value;
        renderReportsView(container);
        container.querySelector('#reporte-prioridad-filtro').focus();
      });
    }
    container.querySelector('#reporte-tecnico-filtro').addEventListener('change', event => {
      reportFilterTechnician = event.target.value;
      renderReportsView(container);
      container.querySelector('#reporte-tecnico-filtro').focus();
    });
    container.querySelectorAll('[data-report-view]').forEach(button => {
      button.addEventListener('click', () => {
        reportViewMode = button.dataset.reportView;
        renderReportsView(container);
        container.querySelector(`[data-report-view="${reportViewMode}"]`).focus();
      });
    });
  }

  function reportHistoryDate(value, includeTime = false) {
    const key = window.PreventiTIReportHistory.dateKey(value);
    if (!key) return 'Sin fecha registrada';
    const date = new Date(`${key}T12:00:00`);
    if (Number.isNaN(date.getTime())) return 'Sin fecha registrada';
    const label = date.toLocaleDateString('es-UY', { day: 'numeric', month: 'long', year: 'numeric' });
    const time = String(value).match(/[ T](\d{2}:\d{2})/)?.[1];
    return includeTime && time ? `${label}, ${time}` : label;
  }

  function renderReportHistoryCard(report) {
    const technician = window.PreventiTIReportHistory.technicianFor(report);
    const status = report.status || 'Sin estado registrado';
    const tone = reportStatusPresentation.find(item => item.key === status)?.tone || 'neutral';
    const text = reportDisplayText;
    return `
      <article class="report-history-card" aria-label="Reporte ${text(report.code)}">
        <header class="report-history-card-heading">
          <div><h3>${text(report.code || `Reporte #${report.id}`)}</h3><p>${text(reportHistoryDate(report.created_at, true))}</p></div>
          <div class="report-history-badges"><span class="report-state-badge report-tone--${tone}"><span class="report-state-dot" aria-hidden="true"></span>${text(status)}</span>${reportPriorityBadge(report.priority)}</div>
        </header>
        <dl class="report-history-meta">
          <div><dt>Dispositivo</dt><dd>${text(report.asset_name || 'Sin nombre registrado')}<span class="report-history-secondary">${text(report.asset_code || 'Sin código')} · ID: ${text(report.asset_id ?? 'No registrado')}</span></dd></div>
          <div><dt>Técnico asignado</dt><dd>${text(technician?.name || 'Sin asignar')}${technician ? `<span class="report-history-secondary">${text(technician.historical ? 'Último técnico asignado' : technician.specialty || `ID: ${technician.id}`)}</span>` : ''}</dd></div>
          <div><dt>Informado por</dt><dd>${text(report.creator_name || 'Sin autor registrado')}</dd></div>
          <div><dt>Fecha de cierre</dt><dd>${report.closed_at ? text(reportHistoryDate(report.closed_at, true)) : report.status === 'Finalizado' ? 'Sin fecha registrada' : 'Pendiente de cierre'}</dd></div>
        </dl>
        <dl class="report-history-content">
          <div class="report-history-description"><dt>Reporte generado</dt><dd>${text(report.description || 'Sin descripción registrada.')}</dd></div>
          <div><dt>Informe de intervención</dt><dd>${text(report.action_report || 'Sin informe registrado.')}</dd></div>
          <div><dt>Diagnóstico</dt><dd>${text(report.final_diagnosis || 'Sin diagnóstico registrado.')}</dd></div>
        </dl>
        ${(report.assignment_history || []).length ? `
          <details class="report-history-assignments">
            <summary>Historial de asignaciones (${report.assignment_history.length})</summary>
            <ol>${report.assignment_history.map(item => `<li><strong>${text(item.technician_name)}</strong><span>${text(reportHistoryDate(item.assigned_at, true))} — ${item.finished_at ? text(reportHistoryDate(item.finished_at, true)) : 'Asignación vigente'}</span>${item.reassignment_reason ? `<p>Motivo de reasignación: ${text(item.reassignment_reason)}</p>` : ''}</li>`).join('')}</ol>
          </details>` : ''}
      </article>`;
  }

  function renderReportHistoryResults(container) {
    const reports = window.PreventiTIReportHistory.selectReports(db.reports || [], reportHistoryFilters);
    const pages = Math.max(1, Math.ceil(reports.length / reportHistoryPageSize));
    reportHistoryPage = Math.min(reportHistoryPage, pages);
    const start = (reportHistoryPage - 1) * reportHistoryPageSize;
    const visible = reports.slice(start, start + reportHistoryPageSize);
    const groups = new Map();
    visible.forEach(report => {
      const key = window.PreventiTIReportHistory.dateKey(report.created_at);
      if (!groups.has(key)) groups.set(key, []);
      groups.get(key).push(report);
    });
    const total = (db.reports || []).length;
    container.querySelector('#report-history-count').textContent = `${reports.length} de ${total} ${total === 1 ? 'reporte' : 'reportes'}${reports.length > reportHistoryPageSize ? ` · Mostrando ${start + 1}–${start + visible.length}` : ''}`;
    const activeFilters = ['status', 'asset', 'technician', 'from', 'to'].filter(key => reportHistoryFilters[key]).length;
    container.querySelector('#report-history-toggle').textContent = activeFilters ? `Filtros (${activeFilters})` : 'Filtros';
    container.querySelector('#report-history-results').innerHTML = reports.length ?
      [...groups].map(([date, items]) => `
        <section class="report-history-day" aria-label="${reportDisplayText(reportHistoryDate(date))}">
          <h2>${reportDisplayText(reportHistoryDate(date))}</h2>
          ${items.map(renderReportHistoryCard).join('')}
        </section>`).join('') : `
        <div class="report-history-empty"><h2>${total ? 'No hay reportes que coincidan' : 'Todavía no hay reportes'}</h2><p>${total ? 'Prueba con otra búsqueda o limpia los filtros para ver el historial completo.' : 'Los reportes aparecerán aquí cuando se registren.'}</p></div>`;
    container.querySelector('#report-history-pagination').innerHTML = pages > 1 ? `
      <button type="button" class="report-button report-button--secondary" data-history-page="${reportHistoryPage - 1}" ${reportHistoryPage === 1 ? 'disabled' : ''}>Anterior</button>
      <span>Página ${reportHistoryPage} de ${pages}</span>
      <button type="button" class="report-button report-button--secondary" data-history-page="${reportHistoryPage + 1}" ${reportHistoryPage === pages ? 'disabled' : ''}>Siguiente</button>` : '';
    container.querySelectorAll('[data-history-page]').forEach(button => button.addEventListener('click', () => {
      reportHistoryPage = Number(button.dataset.historyPage);
      renderReportHistoryResults(container);
      const results = container.querySelector('#report-history-results');
      results.focus();
      results.scrollIntoView({ block: 'start' });
    }));
  }

  function renderReportHistoryView(container) {
    const reports = db.reports || [];
    const helpers = window.PreventiTIReportHistory;
    const filters = reportHistoryFilters;
    const technicians = [...new Map(reports.map(helpers.technicianFor).filter(Boolean).map(item => [String(item.id), item])).values()]
      .sort((a, b) => String(a.name).localeCompare(String(b.name), 'es'));
    // Build options from reports, including devices that are no longer in the inventory.
    const inventoryAssets = new Map((db.assets || []).map(asset => [String(asset.id), asset]));
    const assets = [...new Map(reports.map(report => [helpers.assetKey(report), report])).entries()]
      .map(([key, report]) => {
        const inventoryAsset = report.asset_id != null ? inventoryAssets.get(String(report.asset_id)) : null;
        return {
          value: key,
          id: report.asset_id,
          code: report.asset_code,
          name: report.asset_name,
          category_name: report.asset_category || inventoryAsset?.category_name || 'Dispositivos históricos',
          location_name: inventoryAsset?.location_name || '',
        };
      })
      .sort((a, b) => String(a.code).localeCompare(String(b.code), 'es', { numeric: true }));
    const statuses = [...new Set([...reportStatusPresentation.map(item => item.key), ...reports.map(report => report.status || 'Sin estado registrado')])];
    const option = (value, label, selected) => `<option value="${reportDisplayText(value)}" ${value === selected ? 'selected' : ''}>${reportDisplayText(label)}</option>`;
    container.innerHTML = `
      <div class="reports-view report-history-view">
        <div class="reports-heading">
          <div><button type="button" id="close-report-history" class="report-history-back">← Volver a reportes</button><h1 id="report-history-title" tabindex="-1" class="text-2xl font-bold text-slate-900 tracking-tight">Historial de reportes</h1><p class="text-sm text-slate-500 mt-1">Todos los reportes disponibles de los dispositivos, organizados por fecha de creación.</p></div>
        </div>
        <div class="report-history-tools">
          <div class="report-history-toolbar">
            <label class="report-history-search" for="report-history-search">Buscar en el historial<input type="search" id="report-history-search" value="${reportDisplayText(filters.query)}" placeholder="Reporte, dispositivo, técnico o diagnóstico…" autocomplete="off" aria-controls="report-history-results"></label>
            <label for="report-history-sort">Orden por fecha<select id="report-history-sort">${option('newest', 'Más recientes primero', filters.sort)}${option('oldest', 'Más antiguos primero', filters.sort)}</select></label>
            <button type="button" id="report-history-toggle" class="report-button report-button--secondary" aria-expanded="${reportHistoryFiltersOpen}" aria-controls="report-history-filters">Filtros</button>
            <button type="button" id="report-history-clear" class="report-button report-button--secondary">Limpiar búsqueda y filtros</button>
          </div>
          <form id="report-history-filters" class="report-history-filter-panel" ${reportHistoryFiltersOpen ? '' : 'hidden'}>
            <div class="report-history-filter-grid">
              <label for="report-history-status">Estado actual<select id="report-history-status" name="status"><option value="">Todos los estados</option>${statuses.map(status => option(status, status, filters.status)).join('')}</select></label>
              <div><label for="report-history-asset">Dispositivo</label><select id="report-history-asset" name="asset"><option value="">Todos los dispositivos</option></select></div>
              <label for="report-history-technician">Técnico asignado<select id="report-history-technician" name="technician"><option value="">Todos los técnicos</option>${option('unassigned', 'Sin asignar', filters.technician)}${technicians.map(technician => option(String(technician.id), technician.name, filters.technician)).join('')}</select></label>
              <label for="report-history-from">Desde<input type="date" id="report-history-from" name="from" value="${reportDisplayText(filters.from)}"></label>
              <label for="report-history-to">Hasta<input type="date" id="report-history-to" name="to" value="${reportDisplayText(filters.to)}"></label>
            </div>
            <div class="report-history-filter-footer"><p>El rango incluye ambos días y se aplica a la fecha de creación.</p><button type="submit" class="report-button report-button--primary">Aplicar filtros</button></div>
          </form>
        </div>
        <p id="report-history-count" class="report-history-count" role="status" aria-live="polite" aria-atomic="true"></p>
        <div id="report-history-results" tabindex="-1"></div>
        <nav id="report-history-pagination" class="report-history-pagination" aria-label="Páginas del historial"></nav>
      </div>`;
    enhanceAssetSelect(container.querySelector('#report-history-asset'), assets, {
      label: 'Filtrar por dispositivo',
      emptyLabel: 'Todos los dispositivos',
      emptyOptionLabel: 'Todos los dispositivos',
      showEmptyOption: true,
      selectedValue: filters.asset,
      fallbackCategory: 'Dispositivos históricos',
      searchPlaceholder: 'Buscar por código, nombre o categoría…',
    });
    container.querySelector('#close-report-history').addEventListener('click', () => {
      reportHistoryOpen = false;
      renderReportsView(container);
      container.querySelector('#open-report-history').focus();
    });
    container.querySelector('#report-history-search').addEventListener('input', event => {
      reportHistoryFilters.query = event.target.value;
      reportHistoryPage = 1;
      renderReportHistoryResults(container);
    });
    container.querySelector('#report-history-sort').addEventListener('change', event => {
      reportHistoryFilters.sort = event.target.value;
      reportHistoryPage = 1;
      renderReportHistoryResults(container);
    });
    container.querySelector('#report-history-toggle').addEventListener('click', event => {
      reportHistoryFiltersOpen = !reportHistoryFiltersOpen;
      container.querySelector('#report-history-filters').hidden = !reportHistoryFiltersOpen;
      event.currentTarget.setAttribute('aria-expanded', String(reportHistoryFiltersOpen));
    });
    const form = container.querySelector('#report-history-filters');
    const from = form.elements.namedItem('from');
    const to = form.elements.namedItem('to');
    const validateDates = () => to.setCustomValidity(from.value && to.value && from.value > to.value ? 'La fecha hasta debe ser igual o posterior a la fecha desde.' : '');
    from.addEventListener('input', validateDates);
    to.addEventListener('input', validateDates);
    form.addEventListener('submit', event => {
      event.preventDefault();
      validateDates();
      if (!form.reportValidity()) return;
      ['status', 'asset', 'technician', 'from', 'to'].forEach(key => { reportHistoryFilters[key] = form.elements.namedItem(key).value; });
      reportHistoryPage = 1;
      renderReportHistoryResults(container);
    });
    container.querySelector('#report-history-clear').addEventListener('click', () => {
      reportHistoryFilters = { query: '', status: '', asset: '', technician: '', from: '', to: '', sort: reportHistoryFilters.sort };
      reportHistoryPage = 1;
      renderReportHistoryView(container);
      container.querySelector('#report-history-search').focus();
    });
    renderReportHistoryResults(container);
  }

  window.viewReportDetail = function (reportId) {
    const report = (db.reports || []).find(item => item.id === reportId);
    if (!report) return;
    const activeTechnician = report.assigned_technician ? `${report.assigned_technician.name} (${report.assigned_technician.specialty || 'Sin especialidad'})` : 'Sin asignar';
    const history = (report.assignment_history || []).map(item => `- ${item.technician_name}: ${item.assigned_at || 'sin fecha'}${item.finished_at ? ` a ${item.finished_at}` : ' (activo)'}${item.reassignment_reason ? ` — ${item.reassignment_reason}` : ''}`).join('\n') || 'Sin historial de asignaciones.';
    alert(`REPORTE: ${report.code}\n\nActivo: ${report.asset_code} - ${report.asset_name}\nInformado por: ${report.creator_name}\nPrioridad: ${report.priority}\nEstado: ${report.status || 'Recibido/Derivado'}\nTécnico activo: ${activeTechnician}\nFecha: ${report.created_at}\n\nDescripción:\n${report.description}\n\nInforme de accionar:\n${report.action_report || 'Pendiente'}\n\nDiagnóstico final:\n${report.final_diagnosis || 'Pendiente'}\n\nHistorial de asignaciones:\n${history}`);
  };

  window.manageReport = async function (reportId) {
    const report = (db.reports || []).find(item => item.id === reportId);
    if (!report || !window.PreventiTIAPI) return;

    const action = prompt('Seleccione una acción:\n1. Asignar o reasignar técnico\n2. Avanzar el estado del reporte');
    if (!action) return;

    try {
      if (action === '1') {
        const techniciansResponse = await window.PreventiTIAPI.reportTechnicians();
        const technicians = techniciansResponse.data || [];
        const options = technicians.map(item => `${item.id}: ${item.name} — ${item.specialty}`).join('\n');
        const technicianId = Number.parseInt(prompt(`Seleccione el ID del técnico:\n${options}`), 10);
        if (!technicians.some(item => item.id === technicianId)) {
          window.showToast('Selección inválida', 'Debe elegir un técnico disponible.', 'warning');
          return;
        }
        let reason = null;
        if (report.assigned_technician) {
          reason = prompt('Motivo de la reasignación (mínimo 5 caracteres):');
          if (!reason || reason.trim().length < 5) {
            window.showToast('Motivo requerido', 'La reasignación debe conservar su motivo.', 'warning');
            return;
          }
        }
        const response = await window.PreventiTIAPI.assignReport(reportId, technicianId, reason);
        Object.assign(report, response.data);
        saveDB();
        window.showToast('Asignación actualizada', response.message, 'success');
        return;
      }

      if (action === '2') {
        const transitions = {
          'Recibido/Derivado': [{ key: '1', status: 'En curso', label: 'Pasar a En curso' }],
          'En curso': [
            { key: '1', status: 'Diagnóstico', label: 'Pasar a Diagnóstico' },
            { key: '2', status: 'Finalizado', label: 'Finalizar reporte' },
          ],
          'Diagnóstico': [{ key: '1', status: 'Finalizado', label: 'Finalizar reporte' }],
        };
        const available = transitions[report.status || 'Recibido/Derivado'] || [];
        if (!available.length) {
          window.showToast('Sin transición disponible', 'El reporte ya está finalizado o tiene un estado no válido.', 'warning');
          return;
        }
        const selected = prompt(available.map(item => `${item.key}. ${item.label}`).join('\n'));
        const next = available.find(item => item.key === selected);
        if (!next) return;
        let actionReport = null;
        let finalDiagnosis = null;
        if (next.status === 'Diagnóstico' || next.status === 'Finalizado') {
          actionReport = prompt('Informe del accionar realizado (mínimo 5 caracteres):');
          if (!actionReport || actionReport.trim().length < 5) {
            window.showToast('Informe requerido', 'Debe registrar el accionar realizado.', 'warning');
            return;
          }
        }
        if (next.status === 'Diagnóstico') {
          finalDiagnosis = prompt('Diagnóstico final del dispositivo (mínimo 5 caracteres):');
          if (!finalDiagnosis || finalDiagnosis.trim().length < 5) {
            window.showToast('Diagnóstico requerido', 'Debe registrar el diagnóstico final.', 'warning');
            return;
          }
        }
        const response = await window.PreventiTIAPI.transitionReport(reportId, next.status, actionReport, finalDiagnosis);
        Object.assign(report, response.data);
        saveDB();
        window.showToast('Estado actualizado', response.message, 'success');
        return;
      }

      window.showToast('Acción inválida', 'Seleccione 1 o 2.', 'warning');
    } catch (error) {
      window.showToast('No se pudo gestionar el reporte', error.message, 'danger');
    }
  };

  window.openNotifications = function () {
    window.location.hash = 'dashboard';
    setTimeout(() => document.getElementById('panel-notificaciones')?.scrollIntoView({ behavior: 'smooth' }), 50);
  };

  window.openNotification = async function (notificationId) {
    const notification = (db.notifications || []).find(item => item.id === notificationId);
    if (!notification) return;

    if (!notification.read_at && window.PreventiTIAPI) {
      try {
        const response = await window.PreventiTIAPI.markNotificationRead(notificationId);
        Object.assign(notification, response.data);
        saveDB();
      } catch (error) {
        window.showToast('No se pudo actualizar', error.message, 'danger');
        return;
      }
    }

    const report = notification.report;
    if (report) {
      alert(`REPORTE: ${report.code}\n\nActivo: ${report.asset_code} - ${report.asset_name}\nInformado por: ${report.creator_name}\nPrioridad: ${report.priority}\n\nDescripción:\n${report.description}`);
    }
  };

  return { renderReportsView };
}
