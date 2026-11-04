import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

const context = {};
runInNewContext(readFileSync(new URL('../../public/js/lib/report-history.js', import.meta.url), 'utf8'), context);
const { selectReports, technicianFor, assetKey } = context.PreventiTIReportHistory;
const reports = [
  { id: 1, code: 'REP-2026-0001', asset_id: 9, asset_code: 'POS-009', status: 'Finalizado', created_at: '2026-09-02 23:59', description: 'Pantalla de caja apagada', action_report: 'Se reemplazó la fuente', final_diagnosis: 'Falla eléctrica', assigned_technician: { id: 5, name: 'Daniel Sanz' } },
  { id: 2, code: 'REP-2026-0002', asset_id: 10, asset_code: 'IMP-010', status: 'En curso', created_at: '2026-09-03 00:00', description: 'Impresora con atasco', assigned_technician: { id: 6, name: 'Marcos Benítez' } },
  { id: 3, asset_id: 9, status: 'Recibido/Derivado', created_at: '2026-09-02 00:00' },
  { id: 4, asset_id: null, asset_code: 'RET-099', status: 'Finalizado', created_at: '2026-08-01 12:00', assignment_history: [
    { technician_id: 7, technician_name: 'Lucas Peralta', assigned_at: '2026-07-02 10:00', finished_at: '2026-08-01 12:00' },
    { technician_id: 5, technician_name: 'Daniel Sanz', assigned_at: '2026-07-01 09:00', finished_at: '2026-07-02 10:00' },
  ] },
  { id: 5, status: 'Estado histórico', created_at: null },
  { id: 6, asset_id: 9, status: 'Diagnóstico', created_at: '2026-09-02T23:59:00' },
];
const ids = items => Array.from(items, item => item.id);

test('complete history includes every state and retired device, sorts by timestamp and never mutates its source', () => {
  const before = JSON.stringify(reports);
  assert.deepEqual(ids(selectReports(reports)), [2, 6, 1, 3, 4, 5]);
  assert.deepEqual(ids(selectReports(reports, { sort: 'oldest' })), [4, 3, 1, 6, 2, 5]);
  assert.equal(JSON.stringify(reports), before);
});

test('inclusive date bounds include the entire final day and combine with state, device and technician', () => {
  assert.deepEqual(ids(selectReports(reports, { from: '2026-09-02', to: '2026-09-02' })), [6, 1, 3]);
  assert.deepEqual(ids(selectReports(reports, { from: '2026-09-01', to: '2026-09-02', status: 'Finalizado', asset: 'id:9', technician: '5', query: 'electrica fuente' })), [1]);
  assert.deepEqual(ids(selectReports(reports, { from: '2026-09-03', to: '2026-09-02' })), []);
  assert.deepEqual(ids(selectReports(reports, { status: 'Finalizado', asset: 'id:10' })), []);
});

test('search matches identifiers, diagnosis, intervention and technicians without case or accent differences', () => {
  for (const query of ['ELÉCTRICA', 'fuente', 'pos-009', 'REP-2026-0001', 'daniel pantalla']) {
    assert.deepEqual(ids(selectReports(reports, { query })), [1]);
  }
  assert.deepEqual(ids(selectReports(reports, { query: 'marcos benitez' })), [2]);
  assert.deepEqual(ids(selectReports(reports, { query: 'ret-099' })), [4]);
  assert.deepEqual(ids(selectReports(reports, { query: 'no existe' })), []);
});

test('finished reports retain their last technician; active assignments take precedence and missing data stays explicit', () => {
  assert.equal(technicianFor(reports[3]).id, 7);
  assert.equal(technicianFor(reports[3]).historical, true);
  assert.equal(technicianFor({ ...reports[3], assigned_technician: { id: 8, name: 'Otro técnico' } }).id, 8);
  assert.equal(technicianFor({ ...reports[3], status: 'En curso' }), null);
  assert.deepEqual(ids(selectReports(reports, { technician: '7' })), [4]);
  assert.deepEqual(ids(selectReports(reports, { technician: 'unassigned' })), [6, 3, 5]);
  assert.deepEqual(ids(selectReports(reports, { asset: assetKey(reports[3]) })), [4]);
  assert.deepEqual(ids(selectReports([], {})), []);
  assert.deepEqual(ids(selectReports(reports, { query: '  ' })), ids(selectReports(reports)));
});

test('a large history remains complete and records with the same timestamp have deterministic ordering', () => {
  const many = Array.from({ length: 53 }, (_, index) => ({ id: index + 1, created_at: '2026-10-20 12:00' }));
  assert.deepEqual(ids(selectReports(many)), Array.from({ length: 53 }, (_, index) => 53 - index));
});
