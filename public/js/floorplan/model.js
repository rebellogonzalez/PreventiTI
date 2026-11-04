// Pure adapter: inventory identity, operational status and placement stay independent.
export const STATES = {
  operational: { label: 'Operativo', color: '#18a67c', symbol: '●' },
  warning: { label: 'Advertencia', color: '#d99b24', symbol: '▲' },
  incident: { label: 'Incidencia', color: '#ef5969', symbol: '!' },
  offline: { label: 'Fuera de servicio', color: '#8494a8', symbol: '■' },
  disconnected: { label: 'Desconectado', color: '#9a79ce', symbol: '○' },
  unknown: { label: 'Sin información', color: '#99a5b3', symbol: '?' },
};

export function deviceKind(asset) {
  return ({ 1: 'terminal', 2: 'laptop', 4: 'printer', 5: 'display', 6: 'camera', 7: 'climate', 10: 'power' })[asset.category_id] || 'generic';
}

export function stateOf(asset) { return STATES[asset.operational_status] || STATES.unknown; }

export function resolveLayout(data) {
  const placed = [];
  for (const room of data.rooms) {
    const assets = data.assets.filter(a => a.room_id === room.id).sort((a, b) => a.id - b.id);
    const desks = assets.filter(a => ['terminal', 'laptop'].includes(deviceKind(a)));
    const others = assets.filter(a => !desks.includes(a));
    const columns = Math.max(1, Math.ceil(Math.sqrt(desks.length * room.width / room.depth)));
    const rows = Math.max(1, Math.ceil(desks.length / columns));
    // Automatic coordinates are deliberately temporary and never written to inventory.
    const candidates = [];
    for (let i = 0; i < others.length; i++) {
      const perSide = Math.max(1, Math.ceil(others.length / 2));
      candidates.push({ x: .12 + .76 * ((i % perSide) + .5) / perSide, z: i < perSide ? .10 : .89 });
    }
    const occupied = assets.filter(a => a.position).map(a => a.position);
    for (const asset of assets) {
      const index = desks.indexOf(asset);
      let position = asset.position;
      if (!position) {
        position = index >= 0
          ? { x: .17 + .66 * ((index % columns) + .5) / columns, z: .22 + .51 * (Math.floor(index / columns) + .5) / rows }
          : candidates[others.indexOf(asset)];
        // Keep generated placements clear of saved positions, including after an edit.
        const collides = p => occupied.some(q => Math.hypot((p.x - q.x) * room.width, (p.z - q.z) * room.depth) < .8);
        if (collides(position)) {
          outer: for (let z = .15; z <= .85; z += .1) for (let x = .1; x <= .9; x += .1) {
            if (!collides({ x, z })) { position = { x, z }; break outer; }
          }
        }
        position = { ...position, elevation: 0, rotation: 0, source: 'schematic' };
        occupied.push(position);
      }
      placed.push({ ...asset, position, world: { x: room.x + position.x * room.width, y: position.elevation, z: room.z + position.z * room.depth } });
    }
  }
  return { ...data, assets: placed };
}

export function filterAssets(assets, { query = '', category = '', status = '', room = '' }) {
  const normalize = value => String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
  const term = normalize(query).trim();
  return assets.filter(a => (!room || a.room_id === room) && (!category || String(a.category_id) === category)
    && (!status || a.operational_status === status)
    && (!term || normalize([a.name, a.code, a.serial, a.responsible, a.location_name].join(' ')).includes(term)));
}

export const escapeHTML = value => String(value ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
