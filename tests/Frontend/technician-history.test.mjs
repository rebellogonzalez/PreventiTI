import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const moduleURL = source => `data:text/javascript;base64,${Buffer.from(source).toString('base64')}`;
const helperURL = moduleURL(readFileSync(new URL('../../public/js/lib/technician-history.js', import.meta.url), 'utf8'));
const htmlURL = moduleURL(readFileSync(new URL('../../public/js/lib/html.js', import.meta.url), 'utf8'));
const { buildTechnicianHistories } = await import(helperURL);
const renderer = readFileSync(new URL('../../public/js/features/technician-history.js', import.meta.url), 'utf8')
  .replace('../lib/technician-history.js', helperURL).replace('../lib/html.js', htmlURL);
const { renderTechnicianHistory } = await import(moduleURL(renderer));

const technicians = [
  { id: 4, name: 'Jefe técnico', role: 'tecnico_lider', specialty: 'Hardware' },
  { id: 5, name: 'Primer técnico', role: 'tecnico', specialty: 'Hardware', active: false },
  { id: 6, name: 'Segundo técnico', role: 'tecnico', specialty: 'Software', active: true },
  { id: 7, name: 'Técnico sin reportes', role: 'tecnico', specialty: 'Redes/Infraestructura' },
];
const assignment = (technician_id, assigned_at, finished_at = null, reassignment_reason = null) => ({ technician_id, assigned_at, finished_at, reassignment_reason });
const closed = {
  id: 101, code: 'REP-2026-0101', status: 'Finalizado', closed_at: '2026-10-20 17:00',
  assigned_technician: { id: 6, assigned_at: '2026-10-20 09:00' },
  assignment_history: [
    assignment(5, '2026-10-19 08:00', '2026-10-20 09:00', 'Cambio de especialidad'),
    assignment(6, '2026-10-20 09:00'),
  ],
};

test('all specialists appear, including inactive accounts and zero histories, while the chief is excluded', () => {
  const histories = buildTechnicianHistories(technicians, []);
  assert.deepEqual(histories.map(item => item.id), [5, 6, 7]);
  assert.equal(histories[0].active, false);
  for (const item of histories) {
    assert.deepEqual([item.assignedCount, item.activeCount, item.solvedCount], [0, 0, 0]);
    assert.deepEqual(item.reports, []);
  }
});

test('reassigned reports remain in both histories but only the final responsible technician receives the solution', () => {
  const [first, second] = buildTechnicianHistories(technicians, [closed]);
  assert.deepEqual([first.assignedCount, first.activeCount, first.solvedCount], [1, 0, 0]);
  assert.equal(first.reports[0].participation, 'previous');
  assert.equal(first.reports[0].assignments[0].reassignment_reason, 'Cambio de especialidad');
  assert.deepEqual([second.assignedCount, second.activeCount, second.solvedCount], [1, 0, 1]);
  assert.equal(second.reports[0].participation, 'solved');
});

test('returning to a technician preserves every period without double counting and current ownership is live', () => {
  const report = {
    id: 102, status: 'En curso', assigned_technician: { id: 5, assigned_at: '2026-10-21 10:00' },
    assignment_history: [
      assignment(5, '2026-10-19 08:00', '2026-10-20 09:00', 'Derivado'),
      assignment(6, '2026-10-20 09:00', '2026-10-21 10:00', 'Devuelto'),
      assignment(5, '2026-10-21 10:00'),
    ],
  };
  const before = JSON.stringify(report);
  const histories = buildTechnicianHistories(technicians, [closed, report, report]);
  assert.deepEqual(histories[0].reports.map(entry => entry.report.id), [102, 101]);
  assert.deepEqual([histories[0].assignedCount, histories[0].activeCount, histories[0].solvedCount], [2, 1, 0]);
  assert.equal(histories[0].reports[0].assignments.length, 2);
  assert.equal(JSON.stringify(report), before);
  report.status = 'Finalizado';
  const updated = buildTechnicianHistories(technicians, [report])[0];
  assert.deepEqual([updated.activeCount, updated.solvedCount], [0, 1]);
});

test('closed legacy assignments identify the final technician without confusing a transfer with a resolution', () => {
  const report = {
    ...closed, assigned_technician: null,
    assignment_history: [
      assignment(6, '2026-10-20 09:00', '2026-10-20 17:00'),
      assignment(5, '2026-10-19 08:00', '2026-10-20 09:00', 'Derivado'),
    ],
  };
  const histories = buildTechnicianHistories(technicians, [report]);
  assert.equal(histories[0].solvedCount, 0);
  assert.equal(histories[1].solvedCount, 1);
  const transferred = buildTechnicianHistories(technicians, [{ ...report, assignment_history: [assignment(5, '2026-10-19', '2026-10-20', 'Derivado sin responsable posterior registrado')] }]);
  assert.equal(transferred[0].solvedCount, 0);
});

test('unassigned reports stay uncredited and ambiguous or missing dates do not invent a resolving technician', () => {
  const reports = [
    { id: 1, status: 'Recibido/Derivado', assignment_history: [] },
    { id: 2, status: 'Finalizado', assignment_history: [assignment(5, null, '2026-10-20'), assignment(6, null, '2026-10-20')] },
    { id: 3, status: 'En curso', assigned_technician: { id: '6' }, assignment_history: [] },
    { id: 4, status: 'Finalizado', assigned_technician: { id: 4 }, assignment_history: [assignment(5, '2026-10-18', '2026-10-19', 'Derivado al jefe')] },
  ];
  const histories = buildTechnicianHistories(technicians, reports);
  assert.equal(histories[0].solvedCount, 0);
  assert.equal(histories[1].solvedCount, 0);
  assert.equal(histories[1].activeCount, 1);
  assert.ok(histories.every(item => item.reports.every(entry => entry.report.id !== 1)));
});

test('history renders safe text, dates, assignment reasons, inactive accounts and complete report actions', () => {
  const html = renderTechnicianHistory([
    ...technicians, { id: 8, name: '<img src=x onerror=attack()>', specialty: 'Redes & soporte' },
  ], [{ ...closed, asset_name: 'Terminal de caja', description: '<script>attack()</script>', action_report: 'Fuente reemplazada' }]);
  assert.ok(!html.includes('Jefe técnico'));
  assert.ok(!html.includes('<img'));
  assert.ok(!html.includes('<script>'));
  assert.match(html, /&lt;script&gt;attack\(\)&lt;\/script&gt;/);
  assert.match(html, /Cuenta inactiva/);
  assert.match(html, /todavía no tiene reportes asignados/);
  assert.match(html, /20\/10\/2026 · 17:00/);
  assert.match(html, /Cambio de especialidad/);
  assert.match(html, /Fuente reemplazada/);
  assert.match(html, /window\.viewReportDetail\(101\)/);
  assert.match(renderTechnicianHistory([], []), /No hay técnicos especialistas registrados/);
});
