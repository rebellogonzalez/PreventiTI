import test from 'node:test';
import assert from 'node:assert/strict';
import { formatTimestamp } from '../../public/js/lib/timestamps.js';

test('technical timestamps preserve seconds and use 00–23 in the requested zone', () => {
  const options = { timeZone: 'America/Montevideo', includeDate: false };
  assert.equal(formatTimestamp('2026-11-09T03:00:08Z', options), '00:00:08');
  assert.equal(formatTimestamp('2026-11-09T15:00:00Z', options), '12:00:00');
  assert.match(formatTimestamp('2026-11-09T02:59:59Z', { timeZone: options.timeZone }), /08\/11\/2026.*23:59:59/);
  assert.equal(formatTimestamp(null), 'Sin fecha');
});
