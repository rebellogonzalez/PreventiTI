import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

const context = {};
runInNewContext(readFileSync(new URL('../../public/js/lib/inventory.js', import.meta.url), 'utf8'), context);
const { statusFor, selectAssets } = context.PreventiTIInventory;
const assets = [
  { id: 1, code: 'POS-001', serial: 'SN-100', name: 'Terminal de caja', responsible: 'Laura Méndez', category_id: 1, category_name: 'Terminales', location_id: 2, location_name: 'Atención al cliente', active: true, state: 'Operativo', status_alert: 'normal', days_to_maintenance: null },
  { id: 2, code: 'IMP-002', name: 'Impresora láser', category_id: 2, location_id: 2, active: true, state: 'Operativo', status_alert: 'atencion' },
  { id: 3, code: 'POS-003', name: 'Terminal retirada', category_id: 1, location_id: 2, active: false, state: 'Retirado', status_alert: 'normal' },
  { id: 4, code: 'CAM-004', name: 'Cámara IP', category_id: 3, location_id: 3, active: true, state: 'En Mantenimiento', status_alert: 'critica', days_to_maintenance: -4 },
];
const ids = items => Array.from(items, item => item.id);

test('four mutually exclusive states keep an ongoing intervention separate from overdue alerts', () => {
  assert.deepEqual(ids(selectAssets(assets, { status: 'normal' })), [1]);
  assert.deepEqual(ids(selectAssets(assets, { status: 'atencion' })), [2]);
  assert.deepEqual(ids(selectAssets(assets, { status: 'critica' })), [3]);
  assert.deepEqual(ids(selectAssets(assets, { status: 'mantenimiento' })), [4]);
  assert.equal(statusFor({ ...assets[3], state: ' en mantenimiento ' }), 'mantenimiento');
  assert.equal(statusFor({ ...assets[3], active: false }), 'critica');
});

test('missing maintenance dates and consumable readings do not create false attention alerts', () => {
  const base = { active: true, state: 'Operativo', has_consumable: true, days_to_maintenance: null, consumable_level: null };
  assert.equal(statusFor(base), 'normal');
  assert.equal(statusFor({ ...base, days_to_maintenance: 7 }), 'atencion');
  assert.equal(statusFor({ ...base, days_to_maintenance: 0 }), 'atencion');
  assert.equal(statusFor({ ...base, days_to_maintenance: -1 }), 'critica');
  assert.equal(statusFor({ ...base, consumable_level: 20 }), 'atencion');
  assert.equal(statusFor({ ...base, consumable_level: 0 }), 'atencion');
  assert.equal(statusFor({ ...base, consumable_level: 21 }), 'normal');
  assert.equal(statusFor({ ...base, state: 'Fuera de Servicio' }), 'critica');
});

test('search covers device identifiers, names, people, categories and locations without case or accent differences', () => {
  for (const query of ['pos-001', 'SN-100', 'CAJA', 'laura mendez', 'terminales', 'atencion al cliente', '  Laura   caja ']) {
    assert.deepEqual(ids(selectAssets(assets, { query })), [1]);
  }
  assert.deepEqual(ids(selectAssets(assets, { query: 'camara' })), [4]);
  assert.deepEqual(ids(selectAssets(assets, { query: 'no existe' })), []);
  assert.deepEqual(ids(selectAssets(assets, { query: '"<script>' })), []);
  assert.deepEqual(ids(selectAssets([{ id: 5, name: null, serial: null }], { query: 'SN-100' })), []);
});

test('search and all filters intersect without mutating or truncating the inventory', () => {
  const before = JSON.stringify(assets);
  assert.deepEqual(ids(selectAssets(assets, { query: 'mendez', status: 'normal', category: '1', location: '2' })), [1]);
  assert.deepEqual(ids(selectAssets(assets, { query: 'mendez', status: 'mantenimiento' })), []);
  assert.deepEqual(ids(selectAssets(assets, { category: '1', location: '3' })), []);
  assert.deepEqual(ids(selectAssets(assets, { query: '  ' })), [1, 2, 3, 4]);
  assert.deepEqual(ids(selectAssets([])), []);
  assert.equal(JSON.stringify(assets), before);
});

test('inventory and analysis navigation honor the existing backend permissions', () => {
  const links = ['inventario', 'reportes', 'infraestructura', 'estadisticas', 'analisis'].map(route => ({ getAttribute: () => route, style: {} }));
  const groups = [[links[0]], [links[1]], [links[2]], [links[3], links[4]]].map(children => ({ hidden: false, querySelectorAll: () => children }));
  const window = { addEventListener() {} };
  const document = { querySelectorAll: selector => selector === '[data-nav-group]' ? groups : links, addEventListener() {} };
  runInNewContext(readFileSync(new URL('../../public/js/core/api-client.js', import.meta.url), 'utf8'), { window, document });
  window.PreventiTIAPI.applySidebarPermissions(['dashboard', 'activos', 'reportes']);
  assert.deepEqual(links.map(link => link.style.display), ['flex', 'flex', 'none', 'none', 'none']);
  assert.deepEqual(groups.map(group => group.hidden), [false, false, true, true]);
  window.PreventiTIAPI.applySidebarPermissions(['dashboard', 'activos', 'reportes', 'estadisticas']);
  assert.deepEqual(links.map(link => link.style.display), ['flex', 'flex', 'none', 'flex', 'flex']);
  assert.equal(groups[3].hidden, false);
  window.PreventiTIAPI.applySidebarPermissions(['dashboard', 'reportes']);
  assert.deepEqual(links.map(link => link.style.display), ['none', 'flex', 'none', 'none', 'none']);
  assert.deepEqual(groups.map(group => group.hidden), [true, false, true, true]);
});

test('out of service is not a patrimonial retirement', () => {
  const { isRetired } = context.PreventiTIInventory;
  assert.equal(isRetired({ active: false, state: 'Fuera de Servicio' }), false);
  assert.equal(isRetired({ active: false, state: 'Retirado' }), true);
  assert.equal(isRetired({ active: true, state: 'En Mantenimiento' }), false);
});
