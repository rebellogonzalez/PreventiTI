import { FloorplanScene } from './scene.js';
import { formatTimestamp } from '../lib/timestamps.js';
import { STATES, stateOf, resolveLayout, filterAssets, escapeHTML as esc, deviceKind } from './model.js';

const icons = { terminal: '▣', laptop: '▱', printer: '▤', camera: '◉', climate: '≋', display: '▥', power: 'ϟ', generic: '◇' };
const statusBadge = asset => `<span class="fp-status" style="--status:${stateOf(asset).color}">${stateOf(asset).symbol} ${stateOf(asset).label}</span>`;

export function mountFloorplan(container) {
  return new FloorplanView(container);
}

class FloorplanView {
  constructor(container) {
    this.container = container; this.filters = { query: '', category: '', status: '', room: '' };
    this.abort = new AbortController(); this.requestAbort = new AbortController();
    this.canManageAssets = !!window.currentUserProfile?.permissions?.can_manage_assets;
    this.mobileLayout = matchMedia('(max-width: 1023px)');
    this.touchLayout = matchMedia('(pointer: coarse), (max-width: 1023px)');
    container.innerHTML = `<section id="device-map-view" class="fp-app">
      <header class="fp-header"><div><p class="fp-eyebrow">INFRAESTRUCTURA / VISTA ESPACIAL</p><h1>Mapa de operaciones <span>3D</span></h1><p>Un piso. Todos tus activos. El contexto para actuar.</p></div><div class="fp-header-actions">
        ${this.canManageAssets ? '<button type="button" data-action="create-asset" class="fp-primary">Agregar dispositivo</button>' : ''}
        <div class="fp-sync" role="status">Conectando con el inventario…</div></div></header>
      <div class="fp-metrics" aria-label="Resumen del inventario"></div>
      <div class="fp-toolbar">
        <label class="fp-search"><span aria-hidden="true">⌕</span><input type="search" data-filter="query" aria-label="Buscar activos" placeholder="Buscar activo, ID o responsable…"></label>
        <label><span class="fp-sr">Categoría</span><select data-filter="category"><option value="">Todas las categorías</option></select></label>
        <label><span class="fp-sr">Estado</span><select data-filter="status"><option value="">Todos los estados</option>${Object.entries(STATES).map(([k, s]) => `<option value="${k}">${s.label}</option>`).join('')}</select></label>
        <label><span class="fp-sr">Sala</span><select data-filter="room"><option value="">Todas las salas</option></select></label>
        <button data-action="clear" class="fp-text-button">Limpiar</button>
      </div>
      <div class="fp-context" role="status" aria-atomic="true"></div>
      <div class="fp-workspace">
        <div class="fp-stage">
          <div class="fp-stage-top"><div class="fp-breadcrumb"><button data-action="home">Planta principal</button><span class="fp-current-room"></span></div><span class="fp-plan-badge">PLANO ESQUEMÁTICO</span><button class="fp-touch-toggle" data-action="interact" aria-pressed="false">Mover mapa</button></div>
          <div class="fp-canvas"><div class="fp-labels"></div></div>
          <div class="fp-loading" role="status"><span>Preparando el plano y los activos reales…</span><button data-action="retry-scene" hidden>Reintentar mapa</button></div>
          <div class="fp-stage-controls" aria-label="Controles de cámara"><button data-action="home" title="Ver todo el piso" aria-label="Ver todo el piso">⌂</button><button data-action="iso" title="Perspectiva isométrica" aria-label="Perspectiva isométrica">◇</button><button data-action="top" title="Vista superior" aria-label="Vista superior">▦</button><span></span><button data-action="in" aria-label="Acercar">+</button><button data-action="out" aria-label="Alejar">−</button></div>
          <div class="fp-stage-bottom"><span class="fp-gesture-hint">Arrastrar: desplazar · Rueda: zoom · Botón derecho: girar</span><span class="fp-visible-count"></span></div>
        </div>
        <div class="fp-mobile-nav"><button data-action="panel">Ver lista de dispositivos ↓</button></div>
        <aside class="fp-panel" aria-label="Explorador de salas y activos"><button class="fp-mobile-back" data-action="map">↑ Volver al plano</button><div class="fp-panel-content"><p class="fp-empty">Cargando inventario…</p></div></aside>
      </div>
      <footer class="fp-footer"><div class="fp-legend">${Object.values(STATES).map(s => `<span><i style="background:${s.color}"></i>${s.label}</span>`).join('')}</div><button data-action="refresh" class="fp-text-button">↻ Actualizar</button></footer>
      <p class="fp-disclaimer">Las salas conservan la ubicación del inventario. La geometría y las posiciones automáticas son orientativas, pendientes de relevamiento físico.</p>
    </section>`;
    this.root = container.querySelector('.fp-app');
    const signal = this.abort.signal;
    this.mobileLayout.addEventListener('change', () => this.syncInteraction(), { signal });
    this.touchLayout.addEventListener('change', () => this.syncInteraction(), { signal });
    this.syncInteraction();
    const previewTarget = target => target?.closest?.('.fp-room-row, .fp-asset-row');
    for (const type of ['pointerover', 'focusin']) this.root.addEventListener(type, event => {
      if (event.pointerType === 'touch') return;
      const target = previewTarget(event.target);
      if (!target || target === previewTarget(event.relatedTarget)) return;
      this.hover(target.dataset.assetId ? { assetId: Number(target.dataset.assetId) } : { roomId: target.dataset.roomId });
    }, { signal });
    for (const type of ['pointerout', 'focusout']) this.root.addEventListener(type, event => {
      const target = previewTarget(event.target);
      if (target && target !== previewTarget(event.relatedTarget)) this.hover(null);
    }, { signal });
    const handleFilter = event => {
      const form = event.target.closest('.fp-position-form');
      if (form && event.type === 'input') { form.dataset.dirty = 'true'; delete form.dataset.saved; return; }
      if ((event.target.tagName === 'SELECT') !== (event.type === 'change')) return;
      const key = event.target.dataset.filter;
      if (!key || !this.data) return;
      this.filters[key] = event.target.value;
      if (key === 'room') this.selectRoom(event.target.value || null);
      else { this.selectedAsset = null; this.update(); }
    };
    this.root.addEventListener('input', handleFilter, { signal });
    this.root.addEventListener('change', handleFilter, { signal });
    this.root.addEventListener('click', event => {
      const action = event.target.closest('[data-action]')?.dataset.action;
      const room = event.target.closest('[data-room-id]')?.dataset.roomId;
      const asset = event.target.closest('[data-asset-id]')?.dataset.assetId;
      if (room) { this.selectRoom(room); if (this.mobileLayout.matches) this.scrollTo('.fp-stage'); }
      if (asset) { this.selectAsset(Number(asset)); if (this.mobileLayout.matches) this.scrollTo('.fp-panel'); }
      if (action === 'create-asset' && this.canManageAssets) {
        const selectedRoom = this.data?.rooms.find(item => item.id === this.filters.room);
        window.openCreateAsset(selectedRoom?.location_id);
      }
      if (action === 'retire-asset' && this.canManageAssets) {
        const selected = this.data.assets.find(item => item.id === this.selectedAsset);
        if (selected) window.toggleAssetActive(selected.id, selected);
      }
      if (action === 'refresh') this.refresh();
      if (action === 'home') this.selectRoom(null);
      if (action === 'top' || action === 'iso') this.scene?.focus(this.filters.room, action === 'top');
      if (action === 'in') this.scene?.zoom(.8);
      if (action === 'out') this.scene?.zoom(1.2);
      if (action === 'interact') { this.interacting = !this.interacting; this.syncInteraction(); }
      if (action === 'panel') this.scrollTo('.fp-panel');
      if (action === 'map') this.scrollTo('.fp-stage');
      if (action === 'retry-scene') { if (this.data) { this.createScene(); this.update(); } else this.refresh(); }
      if (action === 'back') { this.selectedAsset = null; this.update(); }
      if (action === 'detail' && this.selectedAsset) { window.openModal('modal-detalle-activo'); window.renderAssetDetail(this.selectedAsset); }
      if (action === 'clear') {
        this.filters = { query: '', category: '', status: '', room: '' };
        this.root.querySelectorAll('[data-filter]').forEach(input => { input.value = ''; }); this.selectRoom(null);
      }
    }, { signal });
    this.root.addEventListener('submit', event => {
      if (event.target.matches('.fp-position-form')) { event.preventDefault(); this.savePosition(event.target); }
    }, { signal });
    document.addEventListener('visibilitychange', () => { if (!document.hidden && !this.savingPosition) this.refresh(); }, { signal });
    this.refresh();
    this.timer = setInterval(() => { if (!document.hidden && !this.savingPosition) this.refresh(); }, 30000);
  }

  scrollTo(selector) { this.root.querySelector(selector).scrollIntoView({ block: 'start', behavior: 'auto' }); }

  syncInteraction() {
    const touch = this.touchLayout.matches;
    this.scene?.setInteractive(!touch || !!this.interacting);
    const toggle = this.root.querySelector('.fp-touch-toggle');
    toggle.setAttribute('aria-pressed', String(!!this.interacting));
    toggle.textContent = this.interacting ? 'Terminar movimiento' : 'Mover mapa';
    this.root.querySelector('.fp-gesture-hint').textContent = touch
      ? this.interacting ? 'Un dedo: mover · Dos dedos: zoom y giro' : 'Toca para seleccionar · Desliza para bajar'
      : 'Arrastrar: desplazar · Rueda: zoom · Botón derecho: girar';
    const context = this.root.querySelector('.fp-context');
    const controls = this.root.querySelector('.fp-stage-controls');
    // On a phone the description belongs below the drawing, within reach after a tap.
    if (this.mobileLayout.matches) {
      this.root.querySelector('.fp-stage').after(context); context.after(controls);
    } else {
      this.root.querySelector('.fp-workspace').before(context);
      this.root.querySelector('.fp-stage-bottom').before(controls);
    }
  }

  createScene() {
    const top = this.scene?.topView;
    this.scene?.dispose(); this.scene = null;
    try {
      this.scene = new FloorplanScene(this.root.querySelector('.fp-canvas'), this.root.querySelector('.fp-labels'), {
        room: id => this.selectRoom(id), asset: id => this.selectAsset(id), hover: hit => this.hover(hit),
        error: message => this.showSceneError(message), restored: () => { this.root.querySelector('.fp-loading').hidden = true; },
      });
      this.scene.build(this.data); this.scene.focus(this.filters.room, top, true);
      this.syncInteraction();
      this.root.querySelector('.fp-loading').hidden = true;
    } catch (error) {
      this.scene?.dispose(); this.scene = null;
      this.showSceneError('No se pudo iniciar el mapa 3D. Puedes consultar las salas y dispositivos en la lista.');
      console.error('Floorplan WebGL:', error);
    }
  }

  refresh() {
    if (this.disposed) return Promise.resolve();
    if (!this.refreshPromise) this.refreshPromise = this.fetchData().finally(() => { this.refreshPromise = null; });
    return this.refreshPromise;
  }

  async fetchData() {
    if (this.busy || this.disposed) return;
    this.busy = true;
    try {
      const response = await window.PreventiTIAPI.request('/floorplan', { signal: this.requestAbort.signal });
      if (this.disposed) return;
      const signature = JSON.stringify([response.data.rooms, response.data.assets, response.data.can_edit_positions]);
      const changed = signature !== this.signature;
      this.data = resolveLayout(response.data);
      const previousRoom = this.filters.room;
      const selected = this.data.assets.find(a => a.id === this.selectedAsset);
      if (selected) this.filters.room = selected.room_id;
      if (this.filters.room && !this.data.rooms.some(r => r.id === this.filters.room)) this.filters.room = '';
      if (this.selectedAsset && !this.data.assets.some(a => a.id === this.selectedAsset)) this.selectedAsset = null;
      const needsScene = !this.scene || this.scene.contextLost;
      if (needsScene) this.createScene();
      else if (changed) this.scene.build(this.data);
      if (this.scene && previousRoom !== this.filters.room) this.scene.focus(this.filters.room);
      const categories = [...new Map(this.data.assets.map(a => [a.category_id, a.category_name])).entries()];
      if (this.filters.category && !categories.some(([id]) => String(id) === this.filters.category)) this.filters.category = '';
      this.updateOptions('category', '<option value="">Todas las categorías</option>' + categories.map(([id, name]) => `<option value="${esc(id)}">${esc(name)}</option>`).join(''));
      this.updateOptions('room', '<option value="">Todas las salas</option>' + this.data.rooms.map(r => `<option value="${esc(r.id)}">${esc(r.label)}</option>`).join(''));
      for (const key of ['category', 'room']) {
        const select = this.root.querySelector(`[data-filter="${key}"]`);
        if (select.value !== this.filters[key]) select.value = this.filters[key];
      }
      const time = formatTimestamp(this.data.updated_at, { includeDate: false });
      this.root.querySelector('.fp-sync').textContent = `● Sincronizado ${time} · cada 30 s`;
      this.root.querySelector('.fp-sync').classList.remove('is-stale');
      if (changed || needsScene) this.update();
      this.signature = signature;
    } catch (error) {
      if (this.disposed) return;
      this.root.querySelector('.fp-sync').textContent = this.data ? 'Sin conexión · se conserva la última lectura' : 'Inventario no disponible';
      this.root.querySelector('.fp-sync').classList.add('is-stale');
      if (!this.data) { this.showSceneError(`No se pudo cargar el inventario: ${error.message}. Usa Actualizar para reintentar.`); this.root.querySelector('.fp-panel-content').textContent = 'No se muestran datos de demostración. Inicia sesión y comprueba la conexión con Laravel.'; }
    } finally { this.busy = false; }
  }

  updateOptions(key, html) {
    const select = this.root.querySelector(`[data-filter="${key}"]`);
    if (select.dataset.options !== html) { select.innerHTML = html; select.dataset.options = html; }
  }

  showSceneError(message) {
    const el = this.root.querySelector('.fp-loading'); el.hidden = false;
    el.querySelector('span').textContent = message; el.querySelector('button').hidden = false;
  }

  selectRoom(id) {
    if (!this.data) return;
    this.filters.room = id || ''; this.selectedAsset = null;
    this.root.querySelector('[data-filter="room"]').value = this.filters.room;
    this.scene?.focus(id); this.update();
    this.root.querySelector('.fp-panel').scrollTop = 0;
  }

  selectAsset(id) {
    const asset = this.data?.assets.find(a => a.id === id); if (!asset) return;
    if (this.filters.room !== asset.room_id) this.scene?.focus(asset.room_id);
    this.filters.room = asset.room_id; this.selectedAsset = id;
    this.root.querySelector('[data-filter="room"]').value = asset.room_id;
    this.update();
    this.root.querySelector('.fp-panel').scrollTop = 0;
  }

  update() {
    if (!this.data) return;
    const { assets, rooms } = this.data;
    const visible = filterAssets(assets, this.filters);
    if (this.selectedAsset && !visible.some(a => a.id === this.selectedAsset)) this.selectedAsset = null;
    const room = rooms.find(r => r.id === this.filters.room);
    const operational = assets.filter(a => a.operational_status === 'operational').length;
    const incidents = assets.filter(a => a.active_incidents > 0).length;
    const confirmed = assets.filter(a => a.position.source === 'confirmed').length;
    this.root.querySelector('.fp-metrics').innerHTML = [
      [assets.length, 'Activos en inventario', `${new Set(assets.map(a => a.category_id)).size} categorías registradas`, ''],
      [operational, 'Operativos', 'Estado y prevención al día', 'green'],
      [incidents, 'Con incidencias activas', 'Equipos que requieren atención', 'red'],
      [`${confirmed}/${assets.length}`, 'Posiciones confirmadas', `${rooms.length} espacios · distribución configurable`, 'blue'],
    ].map(([n, label, sub, color]) => `<div class="fp-metric ${color}"><strong>${n}</strong><div><span>${label}</span><small>${sub}</small></div></div>`).join('');
    this.root.querySelector('.fp-current-room').textContent = room ? ` / Sala ${String(rooms.indexOf(room) + 1).padStart(2, '0')}` : ' / Vista general';
    this.root.querySelector('.fp-visible-count').textContent = `${visible.length} de ${assets.length} activos visibles`;
    this.root.querySelector('[data-action="panel"]').textContent = this.selectedAsset ? 'Ver detalle del dispositivo ↓' : 'Ver lista de dispositivos ↓';
    this.scene?.setSelection(this.filters.room, this.selectedAsset, new Set(visible.map(a => a.id)));
    const panel = this.root.querySelector('.fp-panel-content');
    const editor = panel.querySelector('.fp-position-form');
    const locationKey = room ? JSON.stringify([room.id, room.floor, room.location]) : '';
    const preserveEditor = this.selectedAsset && this.data.can_edit_positions && panel.dataset.asset === String(this.selectedAsset)
      && panel.dataset.location === locationKey && editor && !editor.dataset.saved
      && (editor.dataset.dirty || editor.contains(document.activeElement) || this.savingPosition);
    if (preserveEditor) { this.hover(null); return; }
    panel.dataset.asset = this.selectedAsset || ''; panel.dataset.location = locationKey;
    if (this.selectedAsset) {
      panel.innerHTML = this.assetPanel(assets.find(a => a.id === this.selectedAsset));
    } else {
      const roomAssets = room ? assets.filter(a => a.room_id === room.id) : assets;
      panel.innerHTML = `<div class="fp-panel-heading"><p class="fp-eyebrow">${room ? 'SALA SELECCIONADA' : 'EXPLORADOR'}</p><h2>${esc(room?.label || 'Tu instalación')}</h2><p>${esc(room?.purpose || 'Selecciona un espacio para acercarte a sus dispositivos.')}</p></div>
        ${!room ? `<div class="fp-room-list">${rooms.map((r, index) => {
          const list = assets.filter(a => a.room_id === r.id), issues = list.filter(a => a.active_incidents > 0).length;
          return `<button data-room-id="${esc(r.id)}" class="fp-room-row"><i style="background:${esc(r.color)}">${String(index + 1).padStart(2, '0')}</i><span><strong>${esc(r.label)}</strong><small>${list.length} activos${issues ? ` · ${issues} con incidencias` : ''}</small></span><b>↗</b></button>`;
        }).join('')}</div>` : `<div class="fp-room-summary"><strong>${roomAssets.length}</strong> activos en esta sala <button data-action="home">Ver todo el piso ↗</button></div>`}
        <div class="fp-list-title">ACTIVOS ${room ? 'DE LA SALA' : 'DEL PISO'} <span>${visible.length}</span></div>
        <div class="fp-asset-list">${visible.length ? visible.map(a => `<button class="fp-asset-row" data-asset-id="${a.id}"><span class="fp-asset-icon">${icons[deviceKind(a)]}</span><span><strong>${esc(a.code)}</strong><small>${esc(a.name)}</small>${statusBadge(a)}</span><b>›</b></button>`).join('') : '<p class="fp-empty">No hay activos que coincidan con los filtros.</p>'}</div>`;
    }
    this.hover(null);
  }

  assetPanel(asset) {
    const room = this.data.rooms.find(r => r.id === asset.room_id);
    const p = asset.position;
    const canEditPosition = this.data.can_edit_positions && room?.floor && room?.location;
    return `<button data-action="back" class="fp-panel-back">← Activos de la sala</button>
      <div class="fp-asset-heading"><span class="fp-large-icon">${icons[deviceKind(asset)]}</span>${statusBadge(asset)}<h2>${esc(asset.code)}</h2><p>${esc(asset.name)}</p></div>
      <dl class="fp-details"><div><dt>Categoría</dt><dd>${esc(asset.category_name)}</dd></div><div><dt>Estado registrado</dt><dd>${esc(asset.state)}</dd></div><div><dt>Responsable</dt><dd>${esc(asset.responsible)}</dd></div><div><dt>Ubicación del inventario</dt><dd>${esc(asset.location_name)}</dd></div><div><dt>Número de serie</dt><dd>${esc(asset.serial || 'Sin registrar')}</dd></div><div><dt>Próximo mantenimiento</dt><dd>${esc(asset.next_maintenance || 'Sin fecha registrada')}</dd></div>${asset.has_consumable ? `<div><dt>Consumible</dt><dd>${asset.consumable_level ?? 'Sin lectura'}${asset.consumable_level !== null ? '%' : ''}</dd></div>` : ''}</dl>
      <div class="fp-incidents"><h3>Incidencias activas <span>${asset.active_incidents}</span></h3>${asset.reports.length ? asset.reports.map(r => `<article><strong>${esc(r.code)} · ${esc(r.state)}</strong><p>${esc(r.description)}</p></article>`).join('') : `<p>${asset.active_incidents ? 'Hay incidencias activas. Tu perfil no tiene acceso a su descripción.' : 'Sin reportes visibles para este activo.'}</p>`}</div>
      <div class="fp-position"><h3>Posición en la sala</h3><p>${p.source === 'confirmed' ? '✓ Coordenadas confirmadas' : asset.position_stale ? 'Ubicación modificada: confirma una nueva posición.' : '◇ Distribución automática · sin confirmar'}</p>
      ${canEditPosition ? `<details><summary>Ajustar coordenadas</summary><form class="fp-position-form"><p>X y Z: porcentaje del ancho y fondo de la sala. Guardar confirma la posición visual, sin cambiar la ubicación del inventario.</p><div class="fp-position-grid"><label>X (%)<input name="x" type="number" min="5" max="95" step="0.1" value="${(p.x * 100).toFixed(1)}" required></label><label>Z (%)<input name="z" type="number" min="5" max="95" step="0.1" value="${(p.z * 100).toFixed(1)}" required></label><label>Altura (m)<input name="elevation" type="number" min="0" max="4" step="0.05" value="${p.elevation}" required></label><label>Giro (°)<input name="rotation" type="number" min="0" max="360" step="1" value="${p.rotation}" required></label></div><button type="submit" class="fp-primary">Guardar posición</button><p class="fp-form-message" role="status"></p></form></details>` : this.data.can_edit_positions ? '<p>Para confirmar coordenadas, registra primero el piso y la sala del activo en el inventario.</p>' : ''}</div>
      ${asset.can_view_detail ? '<button data-action="detail" class="fp-primary fp-detail-button">Abrir ficha técnica ↗</button>' : ''}
      ${this.canManageAssets ? '<button type="button" data-action="retire-asset" class="fp-retire">Dar de baja dispositivo</button>' : ''}`;
  }

  async savePosition(form) {
    if (this.savingPosition) return;
    const asset = this.data.assets.find(a => a.id === this.selectedAsset); if (!asset) return;
    const room = this.data.rooms.find(r => r.id === asset.room_id);
    const fields = new FormData(form), controls = [...form.querySelectorAll('input, button')];
    this.savingPosition = true; controls.forEach(control => { control.disabled = true; });
    try {
      await window.PreventiTIAPI.request(`/floorplan/assets/${asset.id}/position`, { method: 'PATCH', body: JSON.stringify({
        x: Number(fields.get('x')) / 100, z: Number(fields.get('z')) / 100, elevation: Number(fields.get('elevation')), rotation: Number(fields.get('rotation')), floor: room.floor, room: room.location,
      }), signal: this.requestAbort.signal });
      if (!this.disposed) {
        // A poll started before saving may still contain the previous coordinates.
        await this.refreshPromise;
        form.dataset.saved = 'true';
        await this.refresh();
        if (!this.disposed) {
          if (form.isConnected) this.update();
          window.showToast('Posición guardada', 'Coordenadas vinculadas al activo y a su sala actual.');
        }
      }
    } catch (error) { if (!this.disposed && form.isConnected) form.querySelector('.fp-form-message').textContent = error.message; }
    finally { this.savingPosition = false; controls.forEach(control => { control.disabled = false; }); }
  }

  hover(hit) {
    if (!this.data) return;
    const { assets, rooms } = this.data;
    const asset = assets.find(a => a.id === hit?.assetId)
      || (!hit?.roomId || this.filters.room ? assets.find(a => a.id === this.selectedAsset) : null);
    const room = rooms.find(r => r.id === (asset?.room_id || hit?.roomId || this.filters.room));
    let content;
    if (asset) {
      content = `<div><strong>${esc(asset.code)} · ${esc(asset.name)}</strong><span>${esc(room?.label || asset.location_name)} · ${esc(asset.category_name)}</span></div>${statusBadge(asset)}`;
    } else if (room) {
      const roomAssets = assets.filter(a => a.room_id === room.id);
      content = `<div><strong>Sala ${String(rooms.indexOf(room) + 1).padStart(2, '0')} · ${esc(room.label)}</strong><span>${roomAssets.length} activos · ${roomAssets.filter(a => a.active_incidents > 0).length} con incidencias. Selecciona un dispositivo en el plano o en la lista.</span></div>`;
    } else {
      content = '<div><strong>Identifica salas y dispositivos</strong><span>Los números corresponden al explorador. Señala o selecciona un elemento para ver su nombre.</span></div>';
    }
    // Pointer movement should not repeatedly replace or announce the same description.
    if (content !== this.contextContent) {
      this.root.querySelector('.fp-context').innerHTML = content;
      this.contextContent = content;
    }
  }

  dispose() { this.disposed = true; clearInterval(this.timer); this.abort.abort(); this.requestAbort.abort(); this.scene?.dispose(); }
}
