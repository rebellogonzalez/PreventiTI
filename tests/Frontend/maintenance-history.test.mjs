import test from 'node:test';
import assert from 'node:assert/strict';
import { selectMaintenanceHistory } from '../../public/js/lib/maintenance-history.js';

const assets = [
  { id: 1, code: 'TER-001', name: 'Terminal de caja', category_name: 'Terminales', location_name: 'Administración' },
  { id: 2, code: 'IMP-002', name: 'Impresora láser', category_name: 'Impresión', location_name: 'Atención al cliente' },
];
const history = [
  { id: 10, asset_id: 2, asset_code: 'IMP-002', asset_name: 'Impresora láser', technician_name: 'José Pérez', maintenance_date: '2026-11-02', next_date: '2027-01-01', periodicity_days: 60, detail: 'Revisión del tóner' },
  { id: 9, asset_id: 1, asset_code: 'TER-001', asset_name: 'Terminal de caja', technician_name: 'María López', maintenance_date: '2026-10-01', next_date: '2026-12-30', periodicity_days: 90, detail: 'Limpieza de teclado' },
  { id: 8, asset_id: 99, asset_code: 'RET-099', asset_name: 'Equipo retirado', technician_name: null, maintenance_date: null, next_date: null, periodicity_days: null, detail: null, observations: 'Registrado por Ana Gómez. Próxima revisión calculada: 2025-02-01.' },
];
const ids = query => selectMaintenanceHistory(history, query, assets).map(row => row.id);

test('maintenance search combines words across visible fields without accents or case differences', () => {
  for (const query of ['IMP-002', 'LASER', 'jose perez', 'impresion cliente toner', '  jose   60 dias ']) assert.deepEqual(ids(query), [10]);
  assert.deepEqual(ids('maria administracion teclado'), [9]);
  assert.deepEqual(ids('ana gomez'), [8]);
  assert.deepEqual(ids('impresion teclado'), []);
  assert.deepEqual(ids('no coincide'), []);
});
test('maintenance dates accept ISO and day/month/year, including projected inspections', () => {
  for (const query of ['2026-11-02', '02/11/2026', '2027-01-01', '01/01/2027', '02/11']) assert.deepEqual(ids(query), [10]);
  assert.deepEqual(ids('30/12/2026'), [9]);
  assert.deepEqual(ids('2026-10-01 30/12/2026'), [9]);
});
test('empty searches preserve the complete order and never mutate records or inventory', () => {
  const original = JSON.stringify([history, assets]);
  assert.deepEqual(ids(''), [10, 9, 8]);
  assert.deepEqual(ids('   '), [10, 9, 8]);
  assert.deepEqual(selectMaintenanceHistory([], 'impresora', assets), []);
  assert.equal(JSON.stringify([history, assets]), original);
});
test('historical and incomplete records remain searchable without current inventory entries', () => {
  assert.deepEqual(ids('RET-099'), [8]);
  assert.deepEqual(ids('no registrado'), [8]);
  assert.equal(selectMaintenanceHistory(history, '', [])[0].asset_name, 'Impresora láser');
  assert.equal(selectMaintenanceHistory([{ id: 1, asset_id: 1 }], 'TER-001', assets)[0].asset_name, 'Terminal de caja');
  assert.equal(selectMaintenanceHistory([{ id: 2 }])[0].asset_code, 'Sin código registrado');
});
test('recorded category and location take precedence over current inventory metadata', () => {
  const rows = [{ ...history[0], asset_category: 'Impresión histórica', location_name: 'Depósito antiguo' }];
  assert.equal(selectMaintenanceHistory(rows, 'deposito historica', assets).length, 1);
  assert.equal(selectMaintenanceHistory(rows, 'cliente', assets).length, 0);
});
