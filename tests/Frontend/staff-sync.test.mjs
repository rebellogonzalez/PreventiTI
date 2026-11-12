import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { runInNewContext } from 'node:vm';

test('a personnel change refreshes other sessions after an open form is closed', async () => {
  const events = {};
  let revision = 'initial';
  let reportRevision = 'no-reports';
  let formOpen = false;
  let refreshes = 0;
  const listen = (name, callback) => { events[name] = callback; };
  const window = {
    addEventListener: listen, setInterval() {},
    db: {}, PreventiTIDB: { load: () => ({}) }, refreshSessionUI() { refreshes++; },
  };
  const document = { addEventListener: listen, querySelectorAll: () => [], querySelector: () => formOpen ? {} : null };
  runInNewContext(readFileSync(new URL('../../public/js/core/api-client.js', import.meta.url), 'utf8'), { window, document });
  window.PreventiTIAPI.request = async path => {
    if (path === '/auth/staff-revision') return { staff_revision: revision, report_revision: reportRevision };
    if (path === '/auth/me') return { role: 'empleado', allowed_areas: ['reportes'], permissions: {}, staff_revision: revision, report_revision: reportRevision };
    return { data: [] };
  };
  events.DOMContentLoaded();
  await new Promise(resolve => setImmediate(resolve));
  await events.focus();
  assert.equal(refreshes, 1);
  revision = 'after-hiring';
  formOpen = true;
  await events.visibilitychange();
  assert.equal(refreshes, 1);
  formOpen = false;
  await events.hashchange();
  assert.equal(refreshes, 2);
  await events.focus();
  assert.equal(refreshes, 2);
  reportRevision = 'new-report';
  formOpen = true;
  await events.focus();
  assert.equal(refreshes, 2);
  formOpen = false;
  await events.close();
  assert.equal(refreshes, 3);
  reportRevision = 'new-assignment';
  await events.focus();
  assert.equal(refreshes, 4);
});
