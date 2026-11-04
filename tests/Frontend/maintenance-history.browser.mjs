import { createMaintenance } from '../../public/js/features/maintenance.js';

const fixtureDB = () => ({
  assets: [
    { id: 1, category_name: 'Terminales', location_name: 'Administración' },
    { id: 2, category_name: 'Impresión', location_name: 'Atención al cliente' },
  ],
  maintenances: [
    { id: 10, asset_id: 2, asset_code: 'IMP-002', asset_name: 'Impresora láser', technician_name: 'José Pérez', maintenance_date: '2026-11-02', next_date: '2027-01-01', periodicity_days: 60, detail: 'Revisión del tóner' },
    { id: 9, asset_id: 1, asset_code: 'TER-001', asset_name: 'Terminal de caja', technician_name: 'María López', maintenance_date: '2026-10-01', next_date: '2026-12-30', periodicity_days: 90, detail: 'Limpieza de teclado' },
    { id: 8, asset_id: 99, asset_code: 'RET-099', asset_name: 'Equipo retirado', maintenance_date: '2025-01-01', next_date: null, periodicity_days: 30, detail: 'Última revisión histórica', observations: 'Registrado por Ana Gómez. Próxima revisión calculada: 2025-02-01.' },
  ],
});
const tests = [];
const test = (name, run) => tests.push({ name, run });
const assert = (condition, message) => { if (!condition) throw new Error(message); };
function mount(db = fixtureDB()) {
  const container = document.createElement('div');
  document.querySelector('#fixtures').appendChild(container);
  const view = createMaintenance({ db });
  view.renderMantenimientosView(container);
  return { db, view, container, input: container.querySelector('#maintenance-history-search') };
}
function search(input, query) {
  input.focus(); input.value = query;
  input.dispatchEvent(new Event('input', { bubbles: true }));
}
const count = container => container.querySelector('#maintenance-history-count').textContent;
const result = container => container.querySelector('#maintenance-history-results').textContent;

test('Filtra mientras se escribe y mantiene el campo y su foco', () => {
  const { container, input } = mount();
  assert(count(container) === '3 de 3 registros auditables', 'Total inicial incorrecto');
  search(input, 'impresion jose');
  assert(count(container) === '1 de 3 registros auditables' && result(container).includes('IMP-002'), 'No se filtró el equipo');
  assert(!result(container).includes('TER-001'), 'Quedaron filas que no coinciden');
  assert(input === container.querySelector('#maintenance-history-search') && document.activeElement === input, 'Se perdió el foco o se reemplazó el buscador');
});
test('Busca próximas inspecciones por fecha y permite limpiar la búsqueda', () => {
  const { container, input } = mount();
  search(input, '30/12/2026');
  assert(result(container).includes('TER-001') && count(container).startsWith('1 de'), 'No se encontró la fecha proyectada');
  container.querySelector('#maintenance-history-clear').click();
  assert(input.value === '' && count(container).startsWith('3 de') && document.activeElement === input, 'Limpiar no restauró las filas y el foco');
});
test('Distingue una búsqueda sin coincidencias de un historial vacío', () => {
  const { container, input } = mount();
  search(input, 'no existe');
  assert(count(container).startsWith('0 de 3') && result(container).includes('No se encontraron'), 'Falta el aviso de búsqueda vacía');
  const empty = mount({ assets: [], maintenances: [] });
  assert(count(empty.container).startsWith('0 de 0') && result(empty.container).includes('Todavía no hay'), 'Falta el aviso de historial vacío');
});
test('Conserva la búsqueda al volver a la vista y al sincronizar registros', () => {
  const { container, input, view, db } = mount();
  search(input, 'impresora');
  db.maintenances.unshift({ ...db.maintenances[0], id: 11, maintenance_date: '2026-11-03' });
  view.renderMantenimientosView(container);
  assert(container.querySelector('#maintenance-history-search').value === 'impresora' && count(container).startsWith('2 de 4'), 'Se perdió el filtro o la actualización');
});
test('El historial sigue incluyendo dispositivos retirados fuera del inventario', () => {
  const { container, input } = mount();
  search(input, 'RET-099');
  assert(count(container).startsWith('1 de 3') && result(container).includes('Última revisión histórica'), 'Se perdió el registro histórico');
  search(input, 'ana gomez');
  assert(count(container).startsWith('1 de 3') && result(container).includes('Registrado por Ana Gómez'), 'No se encuentran o no se muestran las observaciones guardadas');
});
test('La búsqueda y los datos se representan como texto seguro', () => {
  const db = fixtureDB();
  db.maintenances[0].detail = '<img src=x onerror=alert(1)>';
  db.maintenances[0].periodicity_days = '<script>malicious()</script>';
  const { container, input, view } = mount(db);
  search(input, '<img');
  view.renderMantenimientosView(container);
  assert(count(container).startsWith('1 de 3') && !container.querySelector('img, script'), 'Se interpretaron datos como HTML');
  assert(container.querySelector('#maintenance-history-search').value === '<img', 'Se perdió el texto al volver a renderizar');
});

let passed = 0;
for (const { name, run } of tests) {
  const item = document.createElement('li');
  try { run(); passed++; item.className = 'pass'; item.textContent = `✓ ${name}`; }
  catch (error) { item.className = 'fail'; item.textContent = `✗ ${name}: ${error.message}`; }
  document.querySelector('#results').appendChild(item);
  document.querySelector('#fixtures').replaceChildren();
}
document.querySelector('#summary').textContent = `${passed}/${tests.length} pruebas del buscador aprobadas`;
createMaintenance({ db: fixtureDB() }).renderMantenimientosView(document.querySelector('#demo'));
