import { openDialog } from '../ui/report-dialog.js';
import { initTaskTimeInput } from '../ui/forms.js';
import { escapeHTML } from '../lib/html.js';
import { todayDate, isCalendarDate, shiftDays, shiftMonth, weekRange, monthCells, calendarMetrics, dayTasks, formatCalendarDate, monthLabel } from '../lib/calendar-dates.js';

export function createCalendar({ db, applySessionPermissions, getNow = () => new Date() }) {
  let knownToday = todayDate(getNow());
  let selectedCalendarDate = knownToday;
  let calendarFilterType = '';
  let activeContainer = null;
  let dayRefreshTimer = null;
  const taskTypes = ['mantenimiento', 'reporte', 'consumible', 'red', 'auditoria'];

  function refreshToday() {
    const today = todayDate(getNow());
    if (today === knownToday) return false;
    // Follow the date while viewing today; preserve deliberately selected history.
    if (selectedCalendarDate === knownToday) selectedCalendarDate = today;
    knownToday = today;
    return true;
  }

  function calendarIsVisible() {
    return !!activeContainer?.querySelector('#calendar-view');
  }

  function scheduleDayRefresh() {
    window.clearTimeout(dayRefreshTimer);
    dayRefreshTimer = window.setTimeout(() => {
      dayRefreshTimer = null;
      if (!calendarIsVisible()) return;
      if (document.visibilityState !== 'hidden' && refreshToday()) renderCalendarioView(activeContainer);
      else scheduleDayRefresh();
    }, 60_000);
  }

  function checkDateOnReturn() {
    if (document.visibilityState === 'hidden' || !calendarIsVisible()) return;
    if (refreshToday()) renderCalendarioView(activeContainer);
  }

  window.addEventListener('focus', checkDateOnReturn);
  document.addEventListener('visibilitychange', checkDateOnReturn);
  window.addEventListener('hashchange', () => {
    if (window.location.hash !== '#calendario') {
      window.clearTimeout(dayRefreshTimer);
      dayRefreshTimer = null;
      activeContainer = null;
    }
  });

  function executionAction(task, canAssign, assigneeOptions) {
    const id = Number(task.id);
    if (task.cancel_reason) return `<span>Cancelada · ${escapeHTML(task.cancel_reason)}</span>`;
    if (!task.completed && task.technician_id == null && canAssign) {
      return `<form class="calendar-assign-form" data-assign-task="${id}">
        ${assigneeOptions ? `<select name="technician" required aria-label="Responsable de ${escapeHTML(task.title)}">
            <option value="">Elegir técnico</option>${assigneeOptions}</select><button class="calendar-today-button">Asignar</button>`
          : '<a href="#usuarios" class="text-sky-700 underline">Agregar una cuenta técnica</a>'}
      </form>`;
    }
    if (task.asset_id && task.type === 'mantenimiento') {
      if (!task.completed) return `<button type="button" class="calendar-today-button" onclick="window.openInspection(${id})">Registrar inspección</button>`;
      if (!task.inspection_id) return 'Completada · registro histórico';
      return `<button type="button" class="calendar-today-button" onclick="window.viewInspection(${Number(task.asset_id)},${Number(task.inspection_id)})">Ver inspección</button>`;
    }
    if (task.asset_id && task.type === 'consumible') {
      if (task.completed) return 'Completada';
      return `<button type="button" class="calendar-today-button" onclick="window.openModalConsumable(${Number(task.asset_id)},${id})">Registrar reposición</button>`;
    }
    return `<button type="button" onclick="window.toggleTaskCompleted(${id})" aria-label="${task.completed ? 'Marcar pendiente' : 'Completar'}: ${escapeHTML(task.title)}" ${task.updating ? 'disabled' : ''} class="calendar-today-button">${task.completed ? '✓ Completada' : 'Completar'}</button>`;
  }

  function renderCalendarioView(container) {
    if (!container) return;
    activeContainer = container;
    refreshToday();
    const allTasks = db.calendarTasks || [];
    const canAssign = window.currentUserProfile?.permissions.can_manage_calendar;
    const unassigned = canAssign ? allTasks.filter(task => !task.completed && !task.cancel_reason && task.technician_id == null) : [];
    const assigneeOptions = (db.taskAssignees || []).map(person =>
      `<option value="${Number(person.id)}">${escapeHTML(person.name)} · ${escapeHTML(person.role_title)}</option>`).join('');
    const counts = calendarMetrics(allTasks.filter(task => !task.cancel_reason), selectedCalendarDate);
    const tasksForSelectedDay = dayTasks(allTasks, selectedCalendarDate, calendarFilterType);
    const tasksByDate = new Map();
    allTasks.forEach(task => {
      if (!tasksByDate.has(task.date)) tasksByDate.set(task.date, []);
      tasksByDate.get(task.date).push(task);
    });
    const inspectionsByDate = new Map();
    const tasksById = new Map(allTasks.map(task => [task.id, task]));
    for (const inspection of db.maintenances || []) {
      const date = inspection.maintenance_date;
      if (inspection.requires_review || date > knownToday || (!canAssign && inspection.technician_id !== db.currentUserId)) continue;
      if (tasksById.get(inspection.task_id)?.date === date) continue;
      if (!inspectionsByDate.has(date)) inspectionsByDate.set(date, []);
      inspectionsByDate.get(date).push(inspection);
    }
    const executions = !calendarFilterType || calendarFilterType === 'mantenimiento' ? inspectionsByDate.get(selectedCalendarDate) || [] : [];
    const selectedLabel = formatCalendarDate(selectedCalendarDate, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    const shortDate = value => formatCalendarDate(value, { day: 'numeric', month: 'short' });

    container.innerHTML = `
      <div id="calendar-view" class="calendar-view space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <div class="flex items-center gap-2">
              <i data-lucide="calendar" class="w-6 h-6 text-sky-600"></i>
              <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Calendario de Tareas y Servicios</h1>
            </div>
            <p class="text-sm text-slate-500 mt-1">Organiza las tareas y consulta la agenda de cada día.</p>
            <p class="calendar-current-date">Hoy · ${escapeHTML(formatCalendarDate(knownToday, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }))}</p>
          </div>
          <button type="button" data-permission="can_manage_calendar" onclick="window.openCalendarTaskModal()" class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white rounded-lg text-xs font-semibold shadow-sm transition">
            <i data-lucide="calendar-plus" class="w-4 h-4"></i> Agendar Tarea
          </button>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div class="bg-white rounded-xl p-4 border border-sky-200 shadow-sm">
            <span class="text-xs font-bold text-sky-800 uppercase tracking-wider">Tareas del día seleccionado</span>
            <div class="calendar-metric-period">${escapeHTML(formatCalendarDate(selectedCalendarDate))}</div>
            <div class="mt-2 flex items-baseline gap-2"><strong id="calendar-count-day" class="text-3xl font-black text-slate-900">${counts.day}</strong><span class="text-xs text-slate-500">programadas</span></div>
          </div>
          <div class="bg-white rounded-xl p-4 border border-slate-200 shadow-sm">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Tareas de la semana</span>
            <div class="calendar-metric-period">${escapeHTML(shortDate(counts.weekStart))} – ${escapeHTML(shortDate(counts.weekEnd))} · lun. a dom.</div>
            <div class="mt-2 flex items-baseline gap-2"><strong id="calendar-count-week" class="text-3xl font-black text-slate-900">${counts.week}</strong><span class="text-xs text-slate-500">en la semana seleccionada</span></div>
          </div>
          <div class="bg-white rounded-xl p-4 border border-slate-200 shadow-sm">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Tareas del mes</span>
            <div class="calendar-metric-period">${escapeHTML(monthLabel(selectedCalendarDate))}</div>
            <div class="mt-2 flex items-baseline gap-2"><strong id="calendar-count-month" class="text-3xl font-black text-slate-900">${counts.month}</strong><span class="text-xs text-slate-500">en el mes seleccionado</span></div>
          </div>
        </div>

        ${unassigned.length ? `<details class="bg-white rounded-xl border border-amber-200 p-4 text-sm">
          <summary class="font-semibold">Pendientes de asignación (${unassigned.length})</summary>
          <ul class="mt-2">${unassigned.map(task => `<li><button type="button" class="py-2 text-left text-sky-700 hover:underline" data-find-task="${Number(task.id)}">
            ${escapeHTML(formatCalendarDate(task.date))} · ${escapeHTML(task.time || '—')} · ${escapeHTML(task.title)}
          </button></li>`).join('')}</ul></details>` : ''}

        <section class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 sm:p-6" aria-labelledby="calendar-month-title">
          <div class="calendar-toolbar">
            <h2 id="calendar-month-title" class="font-bold text-base text-slate-900" aria-live="polite">${escapeHTML(monthLabel(selectedCalendarDate))}</h2>
            <div class="calendar-navigation" aria-label="Navegación del calendario">
              <button type="button" data-calendar-month="-1" class="calendar-nav-button" aria-label="Mes anterior" title="Mes anterior"><span aria-hidden="true">‹</span></button>
              <button type="button" id="calendar-today" class="calendar-today-button" ${selectedCalendarDate === knownToday ? 'disabled' : ''}>Hoy</button>
              <button type="button" data-calendar-month="1" class="calendar-nav-button" aria-label="Mes siguiente" title="Mes siguiente"><span aria-hidden="true">›</span></button>
            </div>
          </div>
          <div class="calendar-legend" aria-label="Tipos de tarea">
            <span><i class="bg-emerald-500"></i>Mantenimiento</span><span><i class="bg-rose-500"></i>Reporte</span><span><i class="bg-amber-500"></i>Insumo</span><span><i class="bg-purple-500"></i>Red</span><span><i class="bg-sky-500"></i>Auditoría</span>
          </div>
          <div class="calendar-grid">
            ${['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'].map(day => `<div class="calendar-day-header">${day}</div>`).join('')}
            ${monthCells(selectedCalendarDate).map(cell => {
              const tasks = dayTasks(tasksByDate.get(cell.date) || [], cell.date);
              const recorded = inspectionsByDate.get(cell.date)?.length || 0;
              const isToday = cell.date === knownToday;
              const isSelected = cell.date === selectedCalendarDate;
              return `
                <button type="button" class="calendar-day-cell ${cell.inMonth ? '' : 'other-month'} ${isSelected ? 'selected' : ''} ${isToday ? 'today' : ''}"
                  data-calendar-date="${cell.date}" tabindex="${isSelected ? '0' : '-1'}" aria-pressed="${isSelected}" ${isToday ? 'aria-current="date"' : ''}
                  aria-label="${escapeHTML(formatCalendarDate(cell.date, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }))}${isToday ? ', hoy' : ''}, ${tasks.length} ${tasks.length === 1 ? 'tarea' : 'tareas'}">
                  <span class="calendar-cell-heading"><span class="calendar-day-number">${cell.day}</span>${isToday ? '<span class="calendar-today-marker">HOY</span>' : ''}</span>
                  <span class="calendar-cell-tasks">
                    ${tasks.slice(0, 3).map(task => `<span class="task-pill task-pill-${taskTypes.includes(task.type) ? task.type : 'auditoria'} ${task.completed ? 'calendar-pill-completed' : ''}" title="${escapeHTML(`${task.time || '—'} ${task.title}${task.completed ? ' · Completada' : ''}`)}"><span class="calendar-pill-time">${escapeHTML(task.time || '—')}</span><span class="truncate">${escapeHTML(task.title)}</span></span>`).join('')}
                    ${tasks.length > 3 ? `<span class="calendar-more-tasks">+${tasks.length - 3} más</span>` : ''}
                  </span>
                  ${recorded ? `<span class="text-xs text-emerald-700">${recorded} insp.</span>` : ''}
                  ${tasks.length ? `<span class="calendar-mobile-count" aria-hidden="true">${tasks.length} ${tasks.length === 1 ? 'tarea' : 'tareas'}</span>` : ''}
                </button>`;
            }).join('')}
          </div>
        </section>

        <section class="bg-white rounded-xl border border-slate-200 shadow-sm p-5" aria-labelledby="calendar-day-title">
          <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-3 border-b border-slate-100">
            <div>
              <h2 id="calendar-day-title" class="font-bold text-slate-900 text-sm" aria-live="polite">Tareas del ${escapeHTML(selectedLabel)}</h2>
              <p class="text-xs text-slate-500 mt-1">Selecciona un día para consultar sus tareas, ordenadas por hora.</p>
            </div>
            <div><label for="cal-filter-type" class="sr-only">Filtrar tareas por tipo</label>
              <select id="cal-filter-type" class="py-1.5 px-3 border border-slate-300 rounded-lg text-xs bg-white focus:outline-none focus:border-sky-500">
                <option value="">Todas las actividades</option>
                ${[['mantenimiento', 'Mantenimientos preventivos'], ['reporte', 'Reportes de situación'], ['consumible', 'Insumos / Consumibles'], ['red', 'Infraestructura de red'], ['auditoria', 'Auditoría / Respaldo DRP']].map(([value, label]) => `<option value="${value}" ${calendarFilterType === value ? 'selected' : ''}>${label}</option>`).join('')}
              </select>
            </div>
          </div>
          ${executions.length ? `<div class="mt-4"><h3 class="text-sm font-bold">Inspecciones registradas</h3>${executions.map(i => `<button class="block text-left py-2 text-sky-700 underline" onclick="window.viewInspection(${Number(i.asset_id)},${Number(i.id)})">Inspección #${Number(i.id)} · ${escapeHTML(i.asset_code)} · ${escapeHTML(i.asset_name)}</button>`).join('')}</div>` : ''}
          <div class="mt-4 divide-y divide-slate-100">
            ${tasksForSelectedDay.length === 0 ? `
              <div class="py-10 text-center text-slate-500 text-xs">
                <i data-lucide="calendar-check" class="w-8 h-8 mx-auto mb-2 text-slate-300"></i>
                ${calendarFilterType && counts.day ? 'No hay tareas de este tipo para el día seleccionado.' : 'No hay tareas programadas para este día.'}
                <div class="mt-3"><button type="button" data-permission="can_manage_calendar" onclick="window.openCalendarTaskModal()" class="text-sky-600 font-semibold hover:underline">+ Agendar una tarea para este día</button></div>
              </div>` : tasksForSelectedDay.map(task => `
              <div data-task-id="${Number(task.id)}" tabindex="-1" class="py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 text-xs ${task.completed ? 'calendar-task-completed' : ''}">
                <div class="flex items-start gap-3 min-w-0">
                  <div class="font-mono font-bold text-slate-700 bg-slate-100 px-2.5 py-1 rounded text-center min-w-[55px]">${escapeHTML(task.time || '—')}</div>
                  <div class="min-w-0">
                    <h3 class="font-bold text-slate-900 text-xs break-words">${escapeHTML(task.title)}</h3>
                    <div class="mt-1 flex flex-wrap items-center gap-3 text-[11px] text-slate-500">
                      <span>Tipo: <b class="capitalize text-slate-700">${escapeHTML(task.category || task.type)}</b></span>
                      <span>Resp.: <b>${escapeHTML(task.technician || 'Sin asignar')}</b></span>
                      ${task.report_id ? `<button type="button" class="text-sky-700 underline" onclick="window.viewReportDetail(${Number(task.report_id)})">Reporte #${Number(task.report_id)}</button>` : ''}
                      ${task.asset ? `<span>Activo: <b class="font-mono text-sky-700">${escapeHTML(task.asset)}</b></span>` : ''}
                    </div>
                  </div>
                </div>
                <div class="flex flex-wrap items-center gap-2 self-end sm:self-center">
                  <span class="px-2 py-0.5 rounded text-[10px] font-bold ${task.priority === 'Critica' ? 'bg-rose-100 text-rose-800' : task.priority === 'Alta' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700'}">${escapeHTML(task.priority)}</span>
                  ${canAssign && !task.completed && !task.cancel_reason ? `<button type="button" class="calendar-today-button" onclick="window.editCalendarTask(${Number(task.id)})">Gestionar visita</button>` : ''}
                  ${executionAction(task, canAssign, assigneeOptions)}
                </div>
              </div>`).join('')}
          </div>
        </section>
      </div>`;

    applySessionPermissions();
    window.lucide?.createIcons();
    container.querySelectorAll('[data-find-task]').forEach(button => button.addEventListener('click', () => {
      const task = unassigned.find(item => item.id === Number(button.dataset.findTask));
      calendarFilterType = '';
      window.selectCalendarDay(task.date);
      container.querySelector(`[data-assign-task="${task.id}"] :is(select, a)`)?.focus();
    }));
    container.querySelectorAll('[data-assign-task]').forEach(form => form.addEventListener('submit', async event => {
      event.preventDefault();
      const button = form.querySelector('button');
      if (button.disabled) return;
      button.disabled = true;
      try {
        const response = await window.PreventiTIAPI.request(`/calendario/${form.dataset.assignTask}/asignacion`, {
          method: 'PATCH', body: JSON.stringify({ technician_id: Number(form.elements.technician.value) }),
        });
        window.showToast('Tarea asignada', response.message, 'success');
      } catch (error) {
        window.showToast('No se pudo asignar la tarea', error.message, 'danger');
      } finally {
        button.disabled = false;
        await window.PreventiTIAPI.syncWithBackend().catch(() => {});
      }
    }));
    container.querySelector('#cal-filter-type').addEventListener('change', event => {
      calendarFilterType = event.target.value;
      renderCalendarioView(container);
      container.querySelector('#cal-filter-type')?.focus({ preventScroll: true });
    });
    container.querySelectorAll('[data-calendar-month]').forEach(button => button.addEventListener('click', () => {
      selectedCalendarDate = shiftMonth(selectedCalendarDate, Number(button.dataset.calendarMonth));
      renderCalendarioView(container);
      container.querySelector(`[data-calendar-month="${button.dataset.calendarMonth}"]`)?.focus({ preventScroll: true });
    }));
    container.querySelector('#calendar-today').addEventListener('click', () => window.selectCalendarDay(todayDate(getNow())));
    container.querySelectorAll('[data-calendar-date]').forEach(button => {
      button.addEventListener('click', () => window.selectCalendarDay(button.dataset.calendarDate));
      button.addEventListener('keydown', event => {
        const date = button.dataset.calendarDate;
        const offsets = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 };
        let target;
        if (event.key in offsets) target = shiftDays(date, offsets[event.key]);
        else if (event.key === 'Home') target = weekRange(date).start;
        else if (event.key === 'End') target = weekRange(date).end;
        else if (event.key === 'PageUp') target = shiftMonth(date, -1);
        else if (event.key === 'PageDown') target = shiftMonth(date, 1);
        else return;
        event.preventDefault();
        window.selectCalendarDay(target);
      });
    });
    scheduleDayRefresh();
  }

  window.selectCalendarDay = function (dateStr) {
    if (!isCalendarDate(dateStr)) return;
    refreshToday();
    selectedCalendarDate = dateStr;
    const container = document.getElementById('view-container');
    if (container) {
      renderCalendarioView(container);
      container.querySelector(`[data-calendar-date="${dateStr}"]`)?.focus({ preventScroll: true });
    }
  };

  window.viewCalendarTask = function (taskId) {
    const task = db.calendarTasks.find(t => t.id === taskId);
    if (!task) return;
    calendarFilterType = '';
    selectedCalendarDate = task.date;
    window.location.hash = 'calendario';
    window.selectCalendarDay(task.date);
    requestAnimationFrame(() => document.querySelector(`[data-task-id="${Number(taskId)}"]`)?.focus());
  };

  window.editCalendarTask = function (id) {
    const task = db.calendarTasks.find(t => t.id === id);
    if (!task || !window.currentUserProfile.permissions.can_manage_calendar) return;
    const dialog = openDialog('Gestionar visita');
    const body = dialog.querySelector('.report-dialog-body');
    body.innerHTML = `<form class="report-management-form">
      <strong>${escapeHTML(task.title)}</strong>
      <label>Acción<select name="action"><option value="edit">Reprogramar o reasignar</option><option value="cancel">Cancelar visita</option></select></label>
      <fieldset class="space-y-3">
        <label>Fecha<input required type="date" name="date" value="${escapeHTML(task.date)}"></label>
        <label>Hora (24 h)<input required name="time" inputmode="numeric" pattern="([01][0-9]|2[0-3]):[0-5][0-9]" value="${escapeHTML(task.time || '00:00')}"></label>
        <label>Responsable<select name="tech" required><option value="">Seleccionar</option>${db.taskAssignees.map(t => `<option value="${Number(t.id)}" ${t.id === task.technician_id ? 'selected' : ''}>${escapeHTML(t.name)}</option>`).join('')}</select></label>
      </fieldset>
      <label hidden>Motivo de cancelación<input name="reason" maxlength="255" disabled></label>
      <p role="alert"></p><button class="report-button report-button--primary">Guardar</button></form>`;
    const form = body.querySelector('form');
    const fields = form.elements;
    initTaskTimeInput(fields.time);
    fields.action.addEventListener('change', () => {
      const cancel = fields.action.value === 'cancel';
      form.querySelector('fieldset').hidden = form.querySelector('fieldset').disabled = cancel;
      fields.reason.parentElement.hidden = fields.reason.disabled = !cancel;
      fields.reason.required = cancel;
    });
    form.addEventListener('submit', async event => {
      event.preventDefault();
      const button = form.querySelector('button');
      if (button.disabled) return;
      button.disabled = true;
      const change = fields.action.value === 'cancel' ? { cancel_reason: fields.reason.value.trim() }
        : { fecha: fields.date.value, hora: fields.time.value, technician_id: Number(fields.tech.value) };
      try {
        await window.PreventiTIAPI.request('/calendario/' + id, { method: 'PATCH', body: JSON.stringify({ ...change, expected_version: task.version }) });
      } catch (error) {
        form.querySelector('[role=alert]').textContent = error.message;
        button.disabled = false;
        return;
      }
      dialog.close();
      if (change.fecha) selectedCalendarDate = change.fecha;
      window.showToast('Agenda actualizada', task.title, 'success');
      await window.PreventiTIAPI.syncWithBackend().catch(() => window.showToast('Cambio guardado', 'Recarga la agenda para ver el cambio.', 'warning'));
    });
  };

  window.openCalendarTaskModal = function (date = selectedCalendarDate, assetId = null) {
    refreshToday();
    const dateInput = document.getElementById('tarea-fecha');
    if (dateInput) dateInput.value = date;
    if (assetId) {
      document.getElementById('tarea-tipo').value = 'mantenimiento';
      const select = document.getElementById('tarea-activo');
      select.value = assetId;
      select.dispatchEvent(new Event('change', { bubbles: true }));
    }
    const timeInput = document.getElementById('tarea-hora');
    if (timeInput) timeInput.value = timeInput.defaultValue;
    window.openModal('modal-nueva-tarea-calendario');
  };

  window.toggleTaskCompleted = async function (taskId) {
    const task = db.calendarTasks.find(t => t.id === taskId);
    if (!task || task.updating) return;
    task.updating = true;
    try {
      const response = await window.PreventiTIAPI.toggleCalendarTask(taskId, !task.completed, task.version);
      await window.PreventiTIAPI.syncWithBackend().catch(() => window.showToast('Cambio guardado', 'Recarga la agenda.', 'warning'));
      window.showToast('Estado actualizado', response.message, 'success');
    } catch (error) {
      window.showToast('No se pudo actualizar la tarea', error.message, 'danger');
    } finally {
      task.updating = false;
    }
  };

  return { renderCalendarioView };
}
