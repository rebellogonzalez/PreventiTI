import { enhanceAssetSelect } from '../../public/js/ui/asset-select.js';
import { createForms } from '../../public/js/ui/forms.js';
import { createReports } from '../../public/js/features/reports.js';
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
    <form id="form-nueva-tarea"><label for="tarea-activo">Activo asociado</label><select id="tarea-activo"></select><select id="tarea-tecnico"></select><input id="tarea-fecha" value="2026-11-03"><input id="tarea-hora" value="10:00"><input id="tarea-titulo" value="Revisar equipo"><input id="tarea-tipo" value="mantenimiento"><input id="tarea-prioridad" value="Media"><button type="submit">Guardar</button></form>`;
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
test('Mantenimiento actualiza la proyección, guarda el ID y limpia la proyección', async () => {
  const { calls } = mountForms();
  const form = document.querySelector('#form-registrar-mantenimiento');
  const input = form.querySelector('[role="combobox"]');
  type(input, 'TER-010'); key(input, 'Enter');
  assert(form.querySelector('#mant-calc-preview').textContent.includes('2026-12-02'), 'No se actualizó la próxima fecha');
  form.requestSubmit(); await tick(); await tick();
  assert(calls[0]?.type === 'maintenance' && calls[0].assetId === 1, 'Mantenimiento recibió un ID incorrecto');
  assert(form.querySelector('#mant-calc-preview').textContent.includes('Seleccione un activo'), 'La proyección anterior quedó visible');
});
test('Calendario permite tareas generales y bloquea búsquedas sin confirmar', async () => {
  const { calls } = mountForms();
  const form = document.querySelector('#form-nueva-tarea');
  const input = form.querySelector('[role="combobox"]');
  type(input, 'impresora'); form.requestSubmit(); await tick();
  assert(calls.length === 0, 'Se guardó una búsqueda sin seleccionar');
  key(input, 'Enter'); form.requestSubmit(); await tick(); await tick();
  assert(calls[0]?.asset_id === 2, 'La tarea perdió su asociación');
  form.requestSubmit(); await tick();
  assert(calls[1]?.asset_id === null, 'No se permite agendar una tarea general');
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
