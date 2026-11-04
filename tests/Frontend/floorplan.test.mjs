import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const source = readFileSync(new URL('../../public/js/floorplan/model.js', import.meta.url), 'utf8');
const { resolveLayout, filterAssets, stateOf, escapeHTML } = await import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`);
const rooms = [{ id: 'a', x: 0, z: 0, width: 15, depth: 10 }, { id: 'b', x: 17, z: 0, width: 10, depth: 7 }];
const assets = Array.from({ length: 30 }, (_, i) => ({ id: i + 1, room_id: i < 17 ? 'a' : 'b', category_id: 1, name: `Terminal ${i + 1}`, position: null, operational_status: 'operational' }));

test('every inventory identity appears once, inside its own room without mutating source data', () => {
  const before = JSON.stringify(assets);
  const result = resolveLayout({ rooms, assets });
  assert.equal(result.assets.length, 30);
  assert.equal(new Set(result.assets.map(a => a.id)).size, 30);
  assert.equal(JSON.stringify(assets), before);
  for (const asset of result.assets) {
    const room = rooms.find(r => r.id === asset.room_id);
    assert.ok(asset.world.x > room.x && asset.world.x < room.x + room.width);
    assert.ok(asset.world.z > room.z && asset.world.z < room.z + room.depth);
    assert.equal(asset.position.source, 'schematic');
  }
  assert.deepEqual(resolveLayout({ rooms, assets: [...assets].reverse() }), result);
});

test('confirmed coordinates take precedence, including zero elevation, and do not depend on inventory ordering', () => {
  const position = { x: .4, z: .6, elevation: 0, rotation: 90, source: 'confirmed' };
  const result = resolveLayout({ rooms, assets: [{ ...assets[0], position }] });
  assert.deepEqual(result.assets[0].position, position);
  assert.deepEqual(result.assets[0].world, { x: 6, y: 0, z: 6 });
});

test('category, room, state and accent-insensitive search compose without adding assets', () => {
  const items = [{ ...assets[0], name: 'Técnico Redes', responsible: 'Marcos', code: 'NBK-001' }, assets[18]];
  assert.equal(filterAssets(items, { query: 'tecnico', room: 'a', status: 'operational', category: '1' }).length, 1);
  assert.equal(filterAssets(items, { query: 'NBK-001', room: 'b' }).length, 0);
  assert.equal(filterAssets(items, { query: 'ausente' }).length, 0);
});

test('unknown states remain unknown and inventory text is HTML escaped', () => {
  assert.equal(stateOf({ operational_status: 'new-state' }).label, 'Sin información');
  assert.equal(escapeHTML('<img src=x onerror="attack()">'), '&lt;img src=x onerror=&quot;attack()&quot;&gt;');
});
