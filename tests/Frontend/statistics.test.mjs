import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

function moduleURL(url) {
  const source = readFileSync(url, 'utf8').replace(/from '(\.\.?\/[^']+)'/g, (_, path) => `from '${moduleURL(new URL(path, url))}'`);
  return `data:text/javascript;base64,${Buffer.from(source).toString('base64')}`;
}
const { createStatistics } = await import(moduleURL(new URL('../../public/js/features/statistics.js', import.meta.url)));

test('statistics render server costs, consistent currency and recorded monthly counts without fixed demo totals', t => {
  const originals = { window: globalThis.window, document: globalThis.document, setTimeout: globalThis.setTimeout };
  const charts = [];
  globalThis.window = { Chart: class { static getChart() { return null; } constructor(element, config) { charts.push(config); } } };
  globalThis.document = { getElementById: id => ({ id }) };
  globalThis.setTimeout = callback => callback();
  t.after(() => Object.assign(globalThis, originals));
  const db = {
    budgetMetrics: { annual_budget: 120000, executed_to_date: 31640, remaining_budget: 88360, currency: 'UYU', year: 2026, execution_percent: 26.4, scope: 'Escenario de prueba', breakdown: [{ item: 'Repuestos', amount: 20740 }] },
    serverStats: { technicians_ranking: [], maintenance_months: [{ label: 'oct 2026', completed: 3, scheduled: 8 }] },
    categories: [{ name: 'Terminales', count: 30 }], reports: [], users: [], assets: [],
    componentsReplaced: [{ name: 'Conjunto de baterías', category: 'Protección eléctrica', count: 2, cost: 6650, total: 13300, currency: 'UYU', reason: 'Desgaste' }], recurringIncidents: [],
  };
  const view = { innerHTML: '' };
  const statistics = createStatistics({ db });
  statistics.renderEstadisticasView(view);
  assert.match(view.innerHTML, /120\.000 UYU/);
  assert.match(view.innerHTML, /31\.640 UYU/);
  assert.match(view.innerHTML, /88\.360 UYU/);
  assert.match(view.innerHTML, /30 Activos \/ 1 Categorías/);
  assert.ok(!view.innerHTML.includes('USD'));
  assert.ok(!view.innerHTML.includes('14.250.000'));
  assert.deepEqual(charts[1].data.labels, ['oct 2026']);
  assert.deepEqual(charts[1].data.datasets.map(series => series.data), [[3], [8]]);
  statistics.renderAnalisisView(view);
  assert.match(view.innerHTML, /13\.300 UYU/);
  assert.ok(!view.innerHTML.includes('USD'));
});
