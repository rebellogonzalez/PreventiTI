import { enhanceAssetSelect } from '../../public/js/ui/asset-select.js';
import { createForms } from '../../public/js/ui/forms.js';
import { createReports } from '../../public/js/features/reports.js';
import { createCalendar } from '../../public/js/features/calendar.js';
import { createUsers } from '../../public/js/features/users.js';
import { initializeAssetEditor } from '../../public/js/features/asset-editor.js';
import { initializeModals } from '../../public/js/ui/modals.js';
import '../../public/js/lib/report-history.js';

const assets = [
  { id: 1, code: 'TER-010', name: 'Terminal principal', category_name: 'Terminales', location_name: 'Área administrativa', serial: 'SN-10' },
  { id: 2, code: 'IMP-002', name: 'Impresora láser', category_name: 'Impresión', location_name: 'Atención al cliente' },
  { id: 3, code: 'TER-002', name: 'Terminal auxiliar', category_name: 'Terminales', location_name: 'Recepción' },
];
const tests = [];
const test = (name, run) => tests.push({ name, run });
const assert = (condition, message) => { if (!condition) throw new Error(message); };
const tick = () => new Promise(resolve => setTimeout(resolve, 5));
let count = 0;
function mount(items = assets, settings = {}, required = true, container = document.querySelector('#fixtures')) {
  const form = document.createElement('form');
  const id = `asset-test-${++count}`;
  form.innerHTML = `<label for="${id}">Dispositivo</label><select id="${id}" name="asset" ${required ? 'required' : ''}></select><button type="submit">Guardar</button><button type="reset">Restablecer</button>`;
  form.addEventListener('submit', event => event.preventDefault());
  container.appendChild(form);
  const select = form.querySelector('select');
  const component = enhanceAssetSelect(select, items, settings);
  return { form, select, component, input: form.querySelector('[role="combobox"]'), list: form.querySelector('[role="listbox"]') };
}
function type(input, value) {
  input.focus();
  input.value = value;
  input.dispatchEvent(new Event('input', { bubbles: true }));
}
const key = (input, value, extra = {}) => input.dispatchEvent(new KeyboardEvent('keydown', { key: value, bubbles: true, cancelable: true, ...extra }));
const values = list => [...list.querySelectorAll('[role="option"]')].map(option => option.dataset.value).join(',');

test('Carga dispositivos agrupados, ordenados y conserva los identificadores', () => {
  const { select, list } = mount();
  assert(values(list) === '2,3,1', 'Orden o dispositivos incorrectos');
  assert([...list.querySelectorAll('[role="group"]')].map(g => g.getAttribute('aria-label')).join(',') === 'Impresión,Terminales', 'Categorías incorrectas');
  assert(select.options.length === 4, 'El control original perdió opciones');
});
test('Busca por palabras, tildes, categoría, ubicación y serie', () => {
  const { input, list } = mount();
  for (const [query, expected] of [['impresion atencion', '2'], ['ADMINISTRATIVA', '1'], ['sn-10', '1'], ['terminales', '3,1'], ['desconocido', '']]) {
    type(input, query);
    assert(values(list) === expected, `Búsqueda incorrecta: ${query}`);
  }
});
test('El teclado selecciona un ID válido y exige selección en campos obligatorios', () => {
  const { input, select, list, form } = mount();
  assert(!form.checkValidity(), 'El campo obligatorio vacío es válido');
  type(input, 'impresora');
  key(input, 'ArrowDown');
  const active = document.getElementById(input.getAttribute('aria-activedescendant'));
  assert(active?.dataset.value === '2', 'La opción activa no coincide');
  key(input, 'Enter');
  assert(select.value === '2' && form.checkValidity(), 'No se confirmó la selección');
  assert(new FormData(form).get('asset') === '2' && list.hidden, 'No se conservó el ID para enviar');
});
test('Una lista cerrada vuelve a abrirse con todas sus categorías', () => {
  const { input, list, component } = mount();
  type(input, 'impresora');
  key(input, 'ArrowDown'); key(input, 'Enter');
  component.toggle.click();
  assert(values(list) === '2,3,1', 'Se conservó el filtro de la búsqueda anterior');
  key(input, 'Escape');
  input.click();
  assert(!list.hidden, 'El campo enfocado no vuelve a abrir la lista');
});
test('Restablecer borra la selección, el aviso y actualiza los observadores', async () => {
  const { input, select, form, component } = mount();
  form.checkValidity();
  component.choose('1');
  let changes = 0;
  select.addEventListener('change', () => changes++);
  form.reset(); await tick();
  assert(select.value === '' && input.value === '', 'Persistió la selección anterior');
  assert(!input.hasAttribute('aria-invalid'), 'Persistió una advertencia visual');
  assert(changes === 1, 'No se actualizó la calculadora asociada');
});
test('Limpiar un filtro opcional no restaura su selección al actualizar el catálogo', () => {
  const { component, select } = mount(assets, { selectedValue: '1', showEmptyOption: true, emptyLabel: 'Todos los dispositivos' }, false);
  component.choose(''); component.update(assets);
  assert(select.value === '', 'Reapareció el filtro eliminado');
});
test('Una búsqueda sin confirmar no se envía como una tarea general', async () => {
  const { input, form } = mount(assets, { showEmptyOption: true }, false);
  type(input, 'impresora'); input.blur(); await tick();
  assert(input.value === 'impresora', 'Se perdió el texto ingresado');
  assert(!form.checkValidity(), 'Se permite guardar sin confirmar el dispositivo buscado');
  type(input, ''); input.blur(); await tick();
  assert(form.checkValidity(), 'No se permite dejar voluntariamente el campo vacío');
});
test('La sincronización actualiza la etiqueta seleccionada y avisa si desaparece el equipo', () => {
  const { input, component, select, list } = mount();
  component.choose('1'); input.focus();
  component.update(assets.map(a => a.id === 1 ? { ...a, name: 'Terminal renovada' } : a));
  assert(input.value.includes('Terminal renovada'), 'Nombre seleccionado desactualizado');
  assert(values(list) === '2,3,1', 'La etiqueta seleccionada se usó como búsqueda');
  let changes = 0;
  select.addEventListener('change', () => changes++);
  component.update(assets.filter(a => a.id !== 1));
  assert(select.value === '' && input.value === '' && changes === 1, 'La baja dejó estado o dependencias desactualizadas');
});
test('La selección desde inventario se refleja sin escribir en el buscador', () => {
  const { select, input, form } = mount();
  select.value = '3'; select.dispatchEvent(new Event('change', { bubbles: true }));
  assert(input.value.includes('TER-002') && form.checkValidity(), 'Prefijar dispositivo no sincroniza el campo visible');
});
test('Muestra datos como texto y contempla dispositivos históricos sin categoría', () => {
  const { list, input } = mount([{ value: 'code:old', name: '<img src=x onerror=alert(1)>', code: 'RET-001' }], { fallbackCategory: 'Dispositivos históricos' }, false);
  assert(!list.querySelector('img'), 'Se interpretó contenido del dispositivo como HTML');
  type(input, 'historicos');
  assert(values(list) === 'code:old', 'No se encuentra la categoría mostrada');
});
test('La composición de texto no confirma accidentalmente una opción', () => {
  const { input, select } = mount();
  type(input, 'impresora'); key(input, 'ArrowDown');
  key(input, 'Enter', { isComposing: true });
  assert(select.value === '', 'Enter confirmó un equipo durante la composición');
});
test('Cancelación de restablecimiento mantiene selección y búsqueda', async () => {
  const { input, component, form } = mount();
  component.choose('1'); type(input, 'impresora');
  form.addEventListener('reset', event => event.preventDefault());
  form.reset(); await tick();
  assert(input.value === 'impresora', 'Se ejecutó un restablecimiento cancelado');
});

function mountForms() {
  document.querySelector('#fixtures').innerHTML = `
    <form id="form-nuevo-reporte"><label for="reporte-activo-select">Activo afectado</label><select id="reporte-activo-select" required></select><input id="reporte-prioridad-select" value="Alta"><textarea id="reporte-descripcion-input">La pantalla no enciende</textarea><button type="submit">Guardar</button></form>
    <form id="form-registrar-mantenimiento"><label for="mant-asset-select">Activo intervenido</label><select id="mant-asset-select" required></select><input id="mant-date-input" type="date" value="2026-11-02" required><div id="mant-calc-preview"></div><textarea id="mant-detail-input">Limpieza preventiva</textarea><button type="submit">Guardar</button></form>
    <form id="form-nueva-tarea"><label for="tarea-activo">Activo asociado</label><select id="tarea-activo"></select>
    <button type="button" id="tarea-activo-elegir"><span>Buscar o elegir dispositivo</span></button>
    <dialog id="tarea-activo-dialog"><button type="button" autofocus>Cerrar</button></dialog>
    <select id="tarea-tecnico"></select><input id="tarea-fecha" value="2026-11-03"><input id="tarea-hora" value="00:00" required pattern="([01][0-9]|2[0-3]):[0-5][0-9]"><button type="button" data-time-step="-60">−1 h</button><button type="button" data-time-step="60">+1 h</button><button type="button" data-time-step="15">+15 min</button><input id="tarea-titulo" value="Revisar equipo"><input id="tarea-tipo" value="mantenimiento"><input id="tarea-prioridad" value="Media"><button type="submit">Guardar</button></form>`;
  const db = { assets: assets.map(a => ({ ...a, periodicity_days: 30 })), reportAssets: assets.map(a => ({ ...a, active: true })), taskAssignees: [{ id: 5, name: 'Técnico de prueba', role_title: 'Técnico' }], reports: [] };
  const calls = [];
  const context = { db, saveDB() {} };
  const forms = createForms(context);
  initializeModals(context);
  window.currentUserProfile = { permissions: {} };
  window.showToast = () => {};
  window.closeModal = () => {};
  window.PreventiTIAPI = {
    async createReport(assetId) { calls.push({ type: 'report', assetId }); return { data: { id: 1 }, message: 'OK' }; },
    async registerMaintenance(assetId) { calls.push({ type: 'maintenance', assetId }); return { message: 'OK' }; },
    async request(path, options) { calls.push({ type: 'calendar', ...JSON.parse(options.body) }); return { message: 'OK' }; },
    async syncWithBackend() { forms.populateModalSelectors(); },
  };
  forms.populateModalSelectors(); forms.initFormHandlers();
  return { calls, db };
}
test('Nuevo reporte envía el ID elegido desde inventario y se restablece', async () => {
  const { calls, db } = mountForms();
  window.openCreateReportForAsset(2);
  const form = document.querySelector('#form-nuevo-reporte');
  assert(form.querySelector('[role="combobox"]').value.includes('IMP-002'), 'El acceso desde inventario no prefija el dispositivo');
  form.requestSubmit(); await tick(); await tick();
  assert(calls[0]?.type === 'report' && calls[0].assetId === 2 && db.reports.length === 1, 'No se envió el reporte con el ID correcto');
  assert(form.querySelector('select').value === '' && form.querySelector('[role="combobox"]').value === '', 'No se limpió el formulario');
});
test('Reintentar un alta sin confirmación conserva la clave y limpia el formulario al recibir el código', async () => {
  const { db } = mountForms();
  const keys = [];
  const messages = [];
  window.showToast = title => messages.push(title);
  window.currentUserProfile.permissions.can_view_notifications = true;
  window.PreventiTIAPI.createReport = async (id, priority, description, key) => {
    keys.push(key);
    if (keys.length === 1) throw Error('Se perdió la respuesta');
    return { data: { id: 71, code: 'REP-71' }, message: 'Reporte REP-71 registrado.' };
  };
  window.PreventiTIAPI.request = () => { throw Error('Bandeja no disponible'); };
  window.openCreateReportForAsset(1);
  const form = document.querySelector('#form-nuevo-reporte');
  form.requestSubmit(); await tick();
  assert(form.querySelector('select').value === '1', 'Se perdió el borrador al fallar la confirmación');
  form.requestSubmit(); await tick();
  assert(keys.length === 2 && keys[0] && keys[0] === keys[1], 'El reintento cambió de clave');
  assert(db.reports.length === 1 && form.querySelector('select').value === '', 'El alta confirmada no se completó');
  assert(messages.at(-1) === 'Reporte registrado', 'La bandeja ocultó la confirmación del alta');
});

test('Gestionar permite elegir un especialista y revisar una asignación desactualizada', async () => {
  const db = {reports: [{id: 77}]};
  window.currentUserProfile = { permissions: { can_manage_reports: true } };
  createReports({db, saveDB() {}});
  let version = 3, attempted;
  window.PreventiTIAPI = {
    request: async () => ({data: {id: 77, version, code: 'REP-77', status: 'En curso', can_update_status: true, assigned_technician: {id: 5, name: 'Inicial'}}}),
    reportTechnicians: async () => ({data: [{id: 6, name: 'Especialista entrante'}]}),
    assignReport: async (...args) => {
      attempted = args;
      version++;
      throw Object.assign(new Error('El reporte cambió.'), {status: 409});
    },
  };
  await window.manageReport(77);
  let form = document.querySelector('.report-dialog[open] form');
  form.elements.technician.value = '6';
  form.elements.reason.value = 'Cambio de turno.';
  form.requestSubmit(); await tick();
  assert(attempted.join('|') === '77|6|Cambio de turno.|3', 'Faltó la versión que vio la jefatura');
  assert(form.querySelector('[type=submit]').disabled && form.elements.reason.value === 'Cambio de turno.', 'El conflicto permite sobrescribir o pierde el texto');
  form.querySelector('button[type=button]').click(); await tick();
  form = document.querySelector('.report-dialog[open] form');
  assert(form && !form.querySelector('[type=submit]').disabled, 'No se pudo volver a gestionar la versión vigente');
  form.closest('dialog').close(); await tick();
});

test('El especialista gestiona su reporte desde Kanban o Lista y registra diagnóstico y cierre', async () => {
  window.currentUserProfile = { permissions: { can_manage_reports: false } };
  const db = { reports: [
    { id: 1, code: 'REP-1', status: 'Recibido/Derivado', can_update_status: false },
    { id: 2, code: 'REP-2', status: 'En curso', can_update_status: true, assigned_technician: { id: 5, name: 'Especialista' }, version: 1 },
    { id: 3, code: 'REP-3', status: 'Diagnóstico', can_update_status: false },
  ] };
  const record = db.reports[1];
  const container = document.querySelector('#fixtures');
  const view = createReports({ db, saveDB: () => view.renderReportsView(container) });
  const transitions = [];
  window.PreventiTIAPI = {
    request: async () => ({ data: { ...record } }),
    async transitionReport(id, status, note, diagnosis, version) {
      transitions.push([id, status, note, diagnosis, version]);
      const closed = status === 'Finalizado';
      return { data: { ...record, status, action_report: note || record.action_report, version: version + 1, closed, can_update_status: !closed } };
    },
  };
  view.renderReportsView(container);
  for (const mode of ['kanban', 'list']) {
    container.querySelector(`[data-report-view="${mode}"]`).click();
    const buttons = container.querySelectorAll('button[aria-label^="Gestionar"]');
    assert(buttons.length === 1 && buttons[0].getAttribute('aria-label') === 'Gestionar REP-2', 'Se ofreció gestionar un reporte ajeno o recibido');
  }
  container.querySelector('button[aria-label="Gestionar REP-2"]').click(); await tick();
  let form = document.querySelector('.report-dialog[open] form');
  assert([...form.elements.action.options].map(option => option.value).join('|') === 'Diagnóstico|Finalizado', 'El especialista debe poder diagnosticar o cerrar');
  form.elements.note.value = 'Se probó la conexión.';
  form.elements.diagnosis.value = 'Conector averiado.';
  form.requestSubmit(); await tick();
  assert(transitions[0].join('|') === '2|Diagnóstico|Se probó la conexión.|Conector averiado.|1', 'No se guardó el diagnóstico del técnico');
  await window.manageReport(2);
  form = document.querySelector('.report-dialog[open] form');
  assert(form.elements.action.length === 1 && form.elements.action.value === 'Finalizado', 'Acciones incorrectas durante el diagnóstico');
  form.requestSubmit(); await tick();
  assert(transitions[1].join('|') === '2|Finalizado|||2' && !container.querySelector('button[aria-label^="Gestionar"]'), 'No se completó el cierre');
});

test('Mantenimiento actualiza la proyección, guarda el ID y limpia la proyección', async () => {
  const { calls, db } = mountForms();
  const form = document.querySelector('#form-registrar-mantenimiento');
  const input = form.querySelector('[role="combobox"]');
  type(input, 'TER-010'); key(input, 'Enter');
  assert(form.querySelector('#mant-calc-preview').textContent.includes('2026-12-02'), 'No se actualizó la próxima fecha');
  form.requestSubmit(); await tick(); await tick();
  assert(calls[0]?.type === 'maintenance' && calls[0].assetId === 1, 'Mantenimiento recibió un ID incorrecto');
  assert(form.querySelector('#mant-calc-preview').textContent.includes('Seleccione un activo'), 'La proyección anterior quedó visible');
  db.assets[0].periodicity_days = 0;
  type(input, 'TER-010'); key(input, 'Enter');
  assert(form.querySelector('#mant-calc-preview').textContent === 'Sin mantenimiento periódico.', 'Se proyectó una fecha con frecuencia cero');
});
test('Calendario permite tareas generales y bloquea búsquedas sin confirmar', async () => {
  const { calls } = mountForms();
  const form = document.querySelector('#form-nueva-tarea');
  const input = form.querySelector('[role="combobox"]');
  type(input, 'impresora'); form.requestSubmit(); await tick();
  assert(calls.length === 0, 'Se guardó una búsqueda sin seleccionar');
  key(input, 'Enter'); form.requestSubmit(); await tick(); await tick();
  assert(calls[0]?.asset_id === 2, 'La tarea perdió su asociación');
  assert(calls[0].hora === '00:00', 'La hora enviada debe coincidir con la seleccionada');
  form.requestSubmit(); await tick();
  assert(calls[1]?.asset_id === null, 'No se permite agendar una tarea general');
});
test('El selector móvil filtra dispositivos, conserva la selección al cerrar y permite una tarea general', async () => {
  const { calls } = mountForms();
  const trigger = document.getElementById('tarea-activo-elegir');
  const dialog = document.getElementById('tarea-activo-dialog');
  const select = document.getElementById('tarea-activo');
  trigger.click();
  const input = dialog.querySelector('[role="combobox"]');
  const list = dialog.querySelector('[role="listbox"]');
  assert(dialog.open && !list.hidden && input.value === '', 'La lista y la búsqueda deben estar disponibles al abrir');
  type(input, 'impresion atencion');
  assert(values(list) === '2', 'La búsqueda debe mostrar el equipo coincidente');
  list.querySelector('[data-value="2"]').click(); await tick();
  assert(!dialog.open && select.value === '2' && trigger.textContent.includes('IMP-002'), 'No se confirmó el dispositivo');
  trigger.click();
  type(input, 'sin coincidencias');
  assert(list.querySelector('[role="status"]'), 'Falta el mensaje de búsqueda vacía');
  dialog.querySelector('button').click(); await tick();
  select.form.requestSubmit(); await tick(); await tick();
  assert(calls[0].asset_id === 2, 'Cerrar la búsqueda descartó el dispositivo confirmado');
  trigger.click();
  list.querySelector('[data-value=""]').click(); await tick();
  select.form.requestSubmit(); await tick();
  assert(calls[1].asset_id === null, 'La opción general debe enviar una tarea sin dispositivo');
});
test('Calendario completa la hora escrita y ajusta el horario con tres botones', async () => {
  const { calls } = mountForms();
  const input = document.querySelector('#tarea-hora');
  const form = input.form;
  const step = minutes => form.querySelector(`[data-time-step="${minutes}"]`).click();
  input.focus();
  const insert = text => document.execCommand('insertText', false, text);
  for (const [digit, expected] of [['2', '20:00'], ['3', '23:00'], ['4', '23:40'], ['5', '23:45']]) {
    insert(digit);
    assert(input.value === expected, `La máscara debe mostrar ${expected} al escribir ${digit}`);
  }
  input.setSelectionRange(3, 5);
  insert('12');
  assert(input.value === '23:12', 'La edición de los minutos cambió la hora');
  input.select();
  document.execCommand('delete');
  assert(input.value === '00:00', 'Borrar la selección debe restaurar los ceros y el separador');
  insert('23:45');
  assert(input.value === '23:45', 'El pegado debe respetar la máscara');
  step(15);
  assert(input.value === '00:00', 'El ajuste de 15 minutos no cruzó la medianoche');
  step(-60);
  assert(input.value === '23:00', 'No se restó una hora');
  step(60);
  assert(input.value === '00:00', 'No se sumó una hora');
  assert(form.querySelector('#tarea-fecha').value === '2026-11-03', 'El ajuste cambió la fecha');
  type(input, '2460');
  form.requestSubmit(); await tick();
  assert(calls.length === 0, 'Se aceptó una hora inválida');
  type(input, '0907');
  step(15);
  form.requestSubmit(); await tick(); await tick();
  assert(calls.length === 1 && calls[0].hora === '09:22', 'No se envió el horario ajustado');
  assert(input.value === '00:00', 'La siguiente tarea no empieza a las 00:00');
});
test('Historial conserva dispositivos históricos y aplica o limpia su filtro', () => {
  const container = document.createElement('div');
  document.querySelector('#fixtures').appendChild(container);
  const db = { assets: [], reports: [
    { id: 1, code: 'REP-001', asset_id: 2, asset_code: 'IMP-002', asset_name: 'Impresora láser', asset_category: 'Impresión', status: 'En curso', created_at: '2026-11-02 10:00' },
    { id: 2, code: 'REP-002', asset_id: 99, asset_code: 'RET-099', asset_name: 'Equipo histórico', asset_category: 'Terminales', status: 'Finalizado', created_at: '2026-11-01 10:00' },
  ] };
  window.currentUserProfile = { permissions: {} };
  const reports = createReports({ db, getCurrentUser: () => ({ id: 9 }), saveDB() {} });
  reports.renderReportsView(container);
  container.querySelector('#open-report-history').click();
  container.querySelector('#report-history-toggle').click();
  const input = container.querySelector('#report-history-asset-search');
  type(input, 'RET-099');
  container.querySelector('[role="option"][data-value="id:99"]').click();
  container.querySelector('#report-history-filters').requestSubmit();
  assert(container.querySelector('#report-history-count').textContent.startsWith('1 de 2'), 'El filtro histórico no se aplicó');
  reports.renderReportsView(container);
  assert(container.querySelector('#report-history-asset').value === 'id:99', 'Se perdió el filtro al volver a renderizar');
  container.querySelector('#report-history-clear').click();
  assert(container.querySelector('#report-history-count').textContent.startsWith('2 de 2'), 'No se limpió el filtro');
});

test('La baja en el directorio permite localizar y reasignar la tarea pendiente desde el calendario', async () => {
  const person = { id: 41, name: 'Especialista saliente', email: 'saliente@example.test', active: true };
  const task = { id: 12, title: 'Revisar impresora', date: '2030-08-03', time: '19:15', type: 'reporte', technician_id: 41, technician: person.name };
  const db = { currentUserId: 1, users: [person], assets: [], taskAssignees: [{ id: 42, name: 'Especialista entrante', role_title: 'Técnico' }], calendarTasks: [task] };
  document.querySelector('#fixtures').innerHTML = '<div id="view-container"></div>';
  const container = document.querySelector('#view-container');
  window.currentUserProfile = { permissions: { can_manage_calendar: true } };
  const users = createUsers({ db });
  const calendar = createCalendar({ db, applySessionPermissions() {}, getNow: () => new Date('2030-07-01T12:00:00Z') });
  const confirm = window.confirm;
  const calls = [];
  window.confirm = () => true;
  window.showToast = () => {};
  window.PreventiTIAPI = {
    async request(path, options) {
      calls.push([path, options]);
      if (options.method === 'DELETE') {
        person.active = false;
        Object.assign(task, { technician_id: null, technician: null });
        return { data: { tasks: 1 } };
      }
      Object.assign(task, { technician_id: JSON.parse(options.body).technician_id, technician: 'Especialista entrante' });
      return { message: 'Responsable actualizado' };
    },
    async syncWithBackend() {
      if (person.active || calls.length === 1) users.renderUsuariosView(container);
      else calendar.renderCalendarioView(container);
    },
  };
  try {
    users.renderUsuariosView(container);
    container.querySelector('[data-remove-user]').click();
    await tick();
    assert(calls[0][0] === '/auth/users/41' && container.textContent.includes('Deshabilitada'), 'La baja no se reflejó en el directorio');
    calendar.renderCalendarioView(container);
    container.querySelector('[data-find-task="12"]').click();
    const form = container.querySelector('[data-assign-task="12"]');
    assert(form && document.activeElement === form.elements.technician, 'No se localizó la tarea pendiente de otro mes');
    form.elements.technician.value = '42';
    form.requestSubmit();
    await tick();
    assert(calls[1][0] === '/calendario/12/asignacion' && task.technician_id === 42, 'No se envió la reasignación');
    assert(!container.querySelector('[data-find-task]') && container.textContent.includes('Especialista entrante'), 'La agenda no mostró el nuevo responsable');
  } finally {
    window.confirm = confirm;
  }
});

test('El alta exige una zona y permite elegir el modelo y la cuenta o ingresar un modelo nuevo', async () => {
  const response = await fetch('../../resources/views/app/modals/asset-create.blade.php');
  assert(response.ok, 'Falta la plantilla de alta');
  document.querySelector('#fixtures').innerHTML = await response.text();
  const db = {
    users: [
      { id: 8, name: 'María Díaz', email: 'diaz1@example.test', active: true },
      { id: 9, name: 'María Díaz', email: 'diaz2@example.test', active: true },
      { id: 10, name: 'Miguel Ruiz', email: 'ruiz@example.test', active: true },
      { id: 11, name: 'Ana Mora', email: 'mora@example.test', active: true },
      { id: 12, name: 'Mario Torres', email: 'torres@example.test', active: false },
    ],
    categories: [{ id: 1, name: 'Terminales' }, { id: 4, name: 'Impresión' }],
    locations: [{ id: 3, map_label: 'Recepción y espera' }],
    assets: [{ id: 1, name: 'Terminal POS', category_id: 1 }], archivedAssets: [],
  };
  let payload;
  window.currentUserProfile = { permissions: { can_manage_assets: true } };
  window.PreventiTIInventory = { isRetired: () => false };
  window.PreventiTIAPI = {
    async createAsset(data) { payload = data; return { data: { id: 2, name: data.nombre, category_id: data.categoria_id } }; },
    async syncWithBackend() {},
  };
  initializeModals({ db, saveDB() {} });
  initializeAssetEditor({ db, saveDB() {} });
  window.openCreateAsset();
  const form = document.querySelector('#form-nuevo-activo'), fields = form.elements;
  function choose(field, value) {
    field.value = value;
    field.dispatchEvent(new Event('change', { bubbles: true }));
  }
  choose(fields.categoria_id, '1');
  choose(fields.modelo, [...fields.modelo.options].find(option => option.text === 'Terminal POS').value);
  const search = form.querySelector('[role="combobox"]'), list = form.querySelector('[role="listbox"]');
  type(search, 'M');
  assert(!list.hidden && values(list) === '8,9,10', 'No se filtraron los usuarios activos por la inicial del nombre');
  type(search, 'maria');
  assert(values(list) === '8,9' && list.textContent.includes('diaz2@example.test'), 'No se distinguen los homónimos al buscar sin tilde');
  list.querySelector('[data-value="9"]').click();
  assert(fields.frecuencia_service.value === '0', 'La frecuencia inicial debe ser cero');
  fields.serie.value = 'SERIE-NUEVA';
  assert(!fields.ubicacion_id.value && !form.checkValidity(), 'Se asignó una zona sin elegirla');
  choose(fields.ubicacion_id, '3');
  form.requestSubmit();
  await tick();
  assert(payload?.nombre === 'Terminal POS' && payload.responsable_id === 9 && payload.frecuencia_service === 0 && payload.ubicacion_id === 3 && payload.serie === 'SERIE-NUEVA', 'El alta perdió la selección o la frecuencia cero');
  window.openCreateAsset(3);
  choose(fields.categoria_id, '4');
  assert(fields.ubicacion_id.value === '3' && !fields.nombre.disabled && !form.checkValidity(), 'Falta el ingreso de un modelo nuevo desde el mapa');
  fields.nombre.value = 'Nueva impresora';
  fields.serie.value = 'SERIE-IMPRESORA';
  type(search, 'xyz');
  assert(!list.querySelector('[role="option"]') && !form.checkValidity(), 'Se permite guardar una búsqueda sin coincidencias');
  type(search, '');
  assert(list.querySelector('[data-value="other"]') && list.querySelector('[data-value=""]'), 'No se recuperaron las alternativas al borrar la búsqueda');
  form.requestSubmit();
  await tick();
  assert(payload.nombre === 'Nueva impresora' && payload.responsable_id === null && payload.categoria_id === 4, 'Falló el alta sin usuario');
});

test('El directorio filtra por nombre y agrupa todos los dispositivos por cuenta', () => {
  const users = ['Ángela Díaz', 'Ángela Díaz', 'María Pérez'].map((name, index) => ({
    id: index + 1, name, email: index + '@example.test', active: true, role_title: 'Empleado',
  }));
  const db = { users, assets: [
    { name: 'Notebook', code: 'A-1', responsible_id: 1 }, { name: 'Terminal', code: 'A-2', responsible_id: 1 },
    { name: 'Impresora', code: 'B-1', responsible_id: 2 },
  ] };
  const container = document.querySelector('#fixtures');
  createUsers({ db }).renderUsuariosView(container);
  const search = container.querySelector('#users-search');
  const visible = () => [...container.querySelectorAll('[data-user-name]')].filter(row => !row.hidden);
  type(search, 'DIAZ ang');
  const [first, second] = visible();
  assert(visible().length === 2 && first.textContent.includes('A-1') && first.textContent.includes('A-2'), 'Faltan equipos del primer usuario');
  assert(!first.textContent.includes('B-1') && second.textContent.includes('B-1') && !second.textContent.includes('A-1'), 'Se mezclaron personas homónimas');
  type(search, 'maria');
  assert(visible().length === 1 && visible()[0].textContent.includes('Sin dispositivos asignados'), 'Falta la cuenta sin equipo');
  type(search, 'xyz');
  assert(!visible().length && !container.querySelector('[data-search-empty]').hidden, 'Falta el aviso de búsqueda vacía');
  type(search, '');
  assert(visible().length === 3, 'No se restauró el directorio');
});

test('La ficha mantiene la serie completa dentro de la tarjeta y el cierre visible al girar el celular', async () => {
  const response = await fetch('../../resources/views/app/modals/asset-detail.blade.php');
  assert(response.ok, 'No se pudo cargar la plantilla de la ficha');
  const fixtures = document.querySelector('#fixtures');
  fixtures.innerHTML = await response.text();
  const serial = 'SERIE12345'.repeat(10);
  const asset = { nombre: 'Terminal de atención al cliente', serie: serial, estado: 'Operativo',
    responsable: 'Responsable'.repeat(10), ubicacion: 'Planta principal · Atención al cliente', categoria: { nombre: 'Terminales' } };
  window.PreventiTIAPI = { async request() { return { data: { asset, status_alert: 'normal' } }; } };
  window.PreventiTIInventory = { isRetired: () => false };
  window.currentUserProfile = { permissions: { can_edit_assets: true } };
  initializeModals({ db: {}, saveDB() {} });
  await window.viewAssetDetail(1);

  const frame = document.createElement('iframe');
  frame.style.cssText = 'width:320px;height:568px;border:0;max-width:none';
  const loaded = new Promise(resolve => frame.addEventListener('load', resolve, { once: true }));
  const styles = [...document.querySelectorAll('link[rel="stylesheet"], style')].map(node => node.outerHTML).join('');
  frame.srcdoc = `<!doctype html><html lang="es"><head>${styles}</head><body>${fixtures.innerHTML}</body></html>`;
  fixtures.replaceChildren(frame);
  await loaded;
  for (const [width, height] of [[320, 568], [667, 320]]) {
    frame.style.width = `${width}px`;
    frame.style.height = `${height}px`;
    const doc = frame.contentDocument;
    const pane = doc.querySelector('#detalle-activo-content');
    const field = [...pane.querySelectorAll('dd')].find(node => node.textContent === serial);
    assert(field && field.scrollWidth <= field.clientWidth + 1, 'La serie larga desborda la tarjeta');
    assert(pane.scrollWidth <= pane.clientWidth + 1, 'La ficha necesita desplazamiento horizontal');
    const close = doc.querySelector('#modal-detalle-activo button:last-child[onclick*="closeModal"]').getBoundingClientRect();
    assert(close.top >= 0 && close.bottom <= height && close.right <= width, 'El cierre quedó fuera de pantalla');
  }
});

let passed = 0;
for (const { name, run } of tests) {
  const row = document.createElement('li');
  try { await run(); passed++; row.className = 'pass'; row.textContent = `✓ ${name}`; }
  catch (error) { row.className = 'fail'; row.textContent = `✗ ${name}: ${error.message}`; }
  document.querySelector('#results').appendChild(row);
  document.querySelector('#fixtures').replaceChildren();
  await tick();
}
document.querySelector('#summary').textContent = `${passed}/${tests.length} pruebas aprobadas`;
try { mount(assets, {}, true, document.querySelector('#demo')); }
catch (error) { document.querySelector('#demo').append(`Error al montar el selector: ${error.message}`); }
