import * as THREE from '../vendor/three/three.module.min.js';
import { OrbitControls } from '../vendor/three/OrbitControls.js';
import { deviceKind, stateOf } from './model.js';

export class FloorplanScene {
  constructor(host, labels, callbacks) {
    this.host = host; this.labels = labels; this.callbacks = callbacks;
    this.scene = new THREE.Scene();
    this.camera = new THREE.PerspectiveCamera(38, 1, .1, 300);
    this.renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
    this.renderer.setPixelRatio(Math.min(window.devicePixelRatio, 1.7));
    this.renderer.outputColorSpace = THREE.SRGBColorSpace;
    this.renderer.toneMapping = THREE.ACESFilmicToneMapping;
    this.renderer.toneMappingExposure = .95;
    this.renderer.shadowMap.enabled = true;
    this.renderer.shadowMap.type = THREE.PCFSoftShadowMap;
    this.renderer.domElement.setAttribute('aria-label', 'Plano 3D interactivo. Usa las salas y la lista de activos para navegar con teclado.');
    this.renderer.domElement.tabIndex = 0;
    host.prepend(this.renderer.domElement);
    this.controls = new OrbitControls(this.camera, this.renderer.domElement);
    this.controlsConnected = true;
    this.controls.enableDamping = true;
    this.controls.dampingFactor = .12;
    this.controls.minDistance = 4; this.controls.maxDistance = 95;
    this.controls.minPolarAngle = .04; this.controls.maxPolarAngle = Math.PI * .43;
    this.controls.mouseButtons = { LEFT: THREE.MOUSE.PAN, MIDDLE: THREE.MOUSE.DOLLY, RIGHT: THREE.MOUSE.ROTATE };
    this.controls.touches = { ONE: THREE.TOUCH.PAN, TWO: THREE.TOUCH.DOLLY_ROTATE };
    this.controls.addEventListener('change', () => this.invalidate());
    this.controls.addEventListener('start', () => { this.transition = null; this.callbacks.hover(null); });
    this.scene.add(new THREE.HemisphereLight(0xeaf4ff, 0x8a8b89, 2.4));
    const sun = new THREE.DirectionalLight(0xfff3df, 3.3);
    sun.position.set(-12, 35, 20); sun.castShadow = true;
    sun.shadow.mapSize.set(2048, 2048);
    Object.assign(sun.shadow.camera, { left: -45, right: 45, top: 45, bottom: -45, far: 100 });
    sun.shadow.normalBias = .04;
    this.scene.add(sun);
    this.root = new THREE.Group(); this.scene.add(this.root);
    this.raycaster = new THREE.Raycaster();
    this.abort = new AbortController();
    this.pointers = new Set();
    const on = (type, fn) => this.renderer.domElement.addEventListener(type, fn, { signal: this.abort.signal });
    on('pointerdown', e => {
      this.pointers.add(e.pointerId); this.multiTouch ||= this.pointers.size > 1;
      this.down = { id: e.pointerId, x: e.clientX, y: e.clientY, button: e.button, moved: false };
    });
    on('pointerup', e => {
      this.pointers.delete(e.pointerId);
      if (this.multiTouch) { if (!this.pointers.size) this.multiTouch = false; this.down = null; return; }
      const down = this.down; this.down = null;
      if (!down || down.id !== e.pointerId || down.button !== 0 || down.moved || Math.hypot(e.clientX - down.x, e.clientY - down.y) > 5) return;
      const hit = this.pick(e);
      if (hit?.assetId) this.callbacks.asset(hit.assetId);
      else if (hit?.roomId) this.callbacks.room(hit.roomId);
    });
    on('pointercancel', () => { this.pointers.clear(); this.down = null; this.multiTouch = false; });
    on('pointermove', e => {
      if (this.down && Math.hypot(e.clientX - this.down.x, e.clientY - this.down.y) > 5) this.down.moved = true;
      // Touch movement must not change the height of the description above the canvas.
      if (e.pointerType === 'touch' || this.pointers.size) return;
      const hit = this.pick(e); this.renderer.domElement.style.cursor = hit ? 'pointer' : 'grab'; this.callbacks.hover(hit);
    });
    on('pointerleave', () => this.callbacks.hover(null));
    on('webglcontextlost', e => {
      e.preventDefault(); this.contextLost = true; cancelAnimationFrame(this.frame); this.frame = null;
      this.callbacks.error('El mapa 3D se pausó. Puedes reintentarlo o seguir usando la lista de salas y dispositivos.');
    });
    on('webglcontextrestored', () => { this.contextLost = false; this.invalidate(); this.callbacks.restored?.(); });
    on('keydown', e => {
      if (e.key === 'Escape' || e.key === 'Home') { e.preventDefault(); this.callbacks.room(null); }
      if (e.key === '+' || e.key === '=') { e.preventDefault(); this.zoom(.8); }
      if (e.key === '-') { e.preventDefault(); this.zoom(1.2); }
      const shifts = { ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1] };
      if (shifts[e.key]) { e.preventDefault(); const [x, z] = shifts[e.key]; this.camera.position.add(new THREE.Vector3(x, 0, z)); this.controls.target.add(new THREE.Vector3(x, 0, z)); this.invalidate(); }
    });
    this.resize = new ResizeObserver(() => {
      const w = host.clientWidth, h = host.clientHeight;
      this.renderer.setSize(w, h); this.camera.aspect = w / Math.max(1, h); this.camera.updateProjectionMatrix(); this.invalidate();
      if (this.data) this.focus(this.selectedRoom, this.topView, true);
    });
    this.resize.observe(host);
  }

  material(color, roughness = .7) {
    const key = color + ':' + roughness;
    if (!this.materials.has(key)) this.materials.set(key, new THREE.MeshStandardMaterial({ color, roughness }));
    return this.materials.get(key);
  }

  box(parent, w, h, d, x, y, z, color) {
    const mesh = new THREE.Mesh(new THREE.BoxGeometry(w, h, d), this.material(color));
    mesh.position.set(x, y, z); mesh.castShadow = true; mesh.receiveShadow = true; parent.add(mesh); return mesh;
  }

  clear() {
    const geometries = new Set(), materials = new Set();
    this.root.traverse(o => { if (o.geometry) geometries.add(o.geometry); if (o.material) materials.add(o.material); });
    geometries.forEach(g => g.dispose()); materials.forEach(m => m.dispose());
    this.root.clear(); this.labels.replaceChildren();
  }

  build(data) {
    this.clear(); this.data = data; this.materials = new Map(); this.devices = new Map(); this.rooms = new Map(); this.labelItems = [];
    this.bounds = new THREE.Box3();
    data.rooms.forEach(r => { this.bounds.expandByPoint(new THREE.Vector3(r.x, 0, r.z)); this.bounds.expandByPoint(new THREE.Vector3(r.x + r.width, 1, r.z + r.depth)); });
    const size = this.bounds.getSize(new THREE.Vector3()), center = this.bounds.getCenter(new THREE.Vector3());
    this.base = new THREE.Group(); this.root.add(this.base);
    this.box(this.base, size.x + 1, .45, size.z + 1, center.x, -.45, center.z, '#a9b5c1');
    this.box(this.base, size.x + .8, .14, size.z + .8, center.x, -.17, center.z, '#edf0ef');
    // Shared circulation, intentionally free of invented equipment.
    const grid = new THREE.GridHelper(100, 100, '#284451', '#1e333f'); grid.position.set(center.x, -.72, center.z); this.root.add(grid);
    for (const r of data.rooms) {
      const group = new THREE.Group(); group.userData.roomId = r.id; this.root.add(group);
      const floor = this.box(group, r.width - .12, .12, r.depth - .12, r.x + r.width / 2, -.02, r.z + r.depth / 2, r.color);
      floor.material = floor.material.clone();
      const wall = '#fbfaf5';
      if (!r.exterior) {
        if (!r.open_north) this.box(group, r.width, 1.05, .14, r.x + r.width / 2, .49, r.z, wall);
        this.box(group, .14, 1.05, r.depth, r.x, .49, r.z + r.depth / 2, wall);
        // Low cutaway front wall with a real opening towards circulation.
        if (!r.open_south) {
          this.box(group, r.width * .33, .4, .14, r.x + r.width * .165, .18, r.z + r.depth, wall);
          this.box(group, r.width * .33, .4, .14, r.x + r.width * .835, .18, r.z + r.depth, wall);
        }
      }
      if (r.id === 'reception') {
        this.box(group, 3.2, .65, .8, r.x + 3.5, .34, r.z + 2.3, '#75958d');
        this.box(group, 3.2, .6, .18, r.x + 3.5, .85, r.z + 1.95, '#75958d');
        this.box(group, 1.4, .42, .8, r.x + 3.5, .25, r.z + 3.5, '#c3aa8a');
      }
      const label = document.createElement('button'); label.className = 'fp-room-label'; label.dataset.room = r.id;
      label.type = 'button';
      label.addEventListener('click', () => {
        const hadFocus = document.activeElement === label;
        this.callbacks.room(r.id);
        if (hadFocus) this.renderer.domElement.focus({ preventScroll: true });
      });
      for (const event of ['pointerenter', 'focus']) label.addEventListener(event, e => {
        if (e.pointerType !== 'touch') this.callbacks.hover({ roomId: r.id });
      });
      for (const event of ['pointerleave', 'blur']) label.addEventListener(event, () => this.callbacks.hover(null));
      const roomAssets = data.assets.filter(a => a.room_id === r.id);
      const count = `${roomAssets.length} activos · ${roomAssets.filter(a => a.active_incidents > 0).length} con incidencias`;
      label.dataset.number = String(this.labelItems.length + 1).padStart(2, '0');
      label.textContent = label.dataset.number;
      label.setAttribute('aria-label', `Sala ${label.dataset.number}. ${r.label}: ${count}`);
      this.labels.append(label);
      // Keep markers near the room edge, away from the automatic equipment grid.
      this.labelItems.push({ element: label, roomId: r.id, position: new THREE.Vector3(r.x + .65, .25, r.z + r.depth - .65) });
      this.rooms.set(r.id, { group, floor, color: r.color });
    }
    for (const asset of data.assets) this.addDevice(asset);
    this.selection = new THREE.Mesh(new THREE.RingGeometry(.6, .68, 40), new THREE.MeshBasicMaterial({ color: '#168deb', side: THREE.DoubleSide }));
    this.selection.rotation.x = -Math.PI / 2; this.selection.visible = false; this.root.add(this.selection);
    this.invalidate();
  }

  addDevice(asset) {
    const group = new THREE.Group(); group.userData = { assetId: asset.id, roomId: asset.room_id };
    group.position.set(asset.world.x, asset.world.y, asset.world.z); group.rotation.y = THREE.MathUtils.degToRad(asset.position.rotation);
    this.root.add(group);
    const box = (w, h, d, x, y, z, color) => this.box(group, w, h, d, x, y, z, color);
    const kind = deviceKind(asset), dark = '#344553', screen = '#94c3ce', white = '#e9eeed';
    if (kind === 'terminal' || kind === 'laptop') {
      box(1.5, .12, .82, 0, .76, 0, '#cbb89c');
      for (const x of [-.6, .6]) box(.065, .72, .65, x, .36, 0, '#87918f');
      const base = kind === 'terminal' ? 1.04 : .85;
      box(.76, .46, .065, 0, base + .2, -.12, dark);
      box(.65, .35, .012, 0, base + .2, -.078, screen);
      box(.5, .035, .26, 0, .85, .16, dark);
      if (kind === 'terminal') box(.09, .22, .07, 0, .94, -.12, dark);
      box(.5, .12, .5, 0, .43, .8, '#50636a');
      box(.5, .45, .09, 0, .69, 1.03, '#50636a');
      box(.08, .4, .08, 0, .2, .8, '#87918f');
    } else if (kind === 'printer') {
      box(.8, .7, .65, 0, .36, 0, white); box(.8, .2, .65, 0, .8, 0, dark); box(.5, .025, .3, 0, .92, .12, '#fffdf4');
    } else if (kind === 'climate') {
      box(1.15, .45, .32, 0, 1.05, 0, white);
      for (let i = 0; i < 3; i++) box(.9, .035, .015, 0, .95 + i * .07, .17, '#809796');
      box(.06, 1, .06, 0, .5, 0, '#adbfbd');
    } else if (kind === 'camera') {
      box(.09, 1.5, .09, 0, .75, 0, '#91a3aa'); box(.35, .25, .65, 0, 1.55, .15, white); box(.22, .17, .02, 0, 1.55, .49, dark);
    } else if (kind === 'display') {
      box(.08, .85, .08, 0, .42, 0, dark); box(1.2, .75, .1, 0, 1.15, 0, dark); box(1.06, .61, .015, 0, 1.15, .061, '#60a6ad'); box(.5, .05, .4, 0, .04, 0, dark);
    } else {
      box(.48, .75, .55, 0, .4, 0, dark); box(.15, .12, .02, 0, .61, .29, screen);
      for (let i = 0; i < 3; i++) box(.29, .035, .015, 0, .21 + i * .08, .29, '#8197a0');
    }
    const state = stateOf(asset);
    const beacon = new THREE.Mesh(new THREE.SphereGeometry(.12, 10, 8), new THREE.MeshBasicMaterial({ color: state.color }));
    beacon.position.set(.42, 1.7, 0); group.add(beacon);
    const ring = new THREE.Mesh(new THREE.RingGeometry(.32, .40, 24), new THREE.MeshBasicMaterial({ color: state.color, side: THREE.DoubleSide }));
    ring.rotation.x = -Math.PI / 2; ring.position.y = .06; group.add(ring);
    this.devices.set(asset.id, group);
  }

  pick(event) {
    const rect = this.renderer.domElement.getBoundingClientRect();
    this.raycaster.setFromCamera(new THREE.Vector2((event.clientX - rect.left) / rect.width * 2 - 1, -(event.clientY - rect.top) / rect.height * 2 + 1), this.camera);
    for (const hit of this.raycaster.intersectObjects(this.root.children, true)) {
      let o = hit.object, visible = true, data;
      while (o) { if (!o.visible) visible = false; if (o.userData.assetId || o.userData.roomId) data = o.userData; o = o.parent; }
      if (visible && data) return data;
    }
    return null;
  }

  setSelection(roomId, assetId, visibleIds) {
    this.selectedRoom = roomId;
    this.visibleIds = visibleIds;
    this.base.visible = !roomId;
    this.rooms.forEach((r, id) => {
      r.group.visible = !roomId || id === roomId;
      r.floor.material.color.set(id === roomId ? '#d5e8e3' : r.color);
    });
    this.devices.forEach((group, id) => { group.visible = visibleIds.has(id); });
    this.selection.visible = !!assetId && visibleIds.has(assetId);
    if (this.selection.visible) { this.selection.position.copy(this.devices.get(assetId).position); this.selection.position.y += .085; }
    // The breadcrumb and explorer identify the selected room; its marker adds no information.
    this.labels.hidden = !!roomId;
    this.invalidate();
  }

  focus(roomId, top = this.topView || false, immediate = false) {
    if (!this.data || this.bounds.isEmpty()) return;
    this.topView = top;
    const room = this.data.rooms.find(r => r.id === roomId);
    const box = room ? new THREE.Box3(new THREE.Vector3(room.x, 0, room.z), new THREE.Vector3(room.x + room.width, 1, room.z + room.depth)) : this.bounds;
    const center = box.getCenter(new THREE.Vector3()); center.y = 0;
    const offset = (top ? new THREE.Vector3(0, 1, .015) : new THREE.Vector3(.36, 1.1, 1.2)).normalize();
    const right = new THREE.Vector3().crossVectors(this.camera.up, offset).normalize();
    const up = new THREE.Vector3().crossVectors(offset, right).normalize();
    const tan = Math.tan(THREE.MathUtils.degToRad(this.camera.fov / 2));
    const horizontal = tan * this.camera.aspect * Math.max(.4, 1 - 64 / Math.max(1, this.host.clientWidth));
    const vertical = tan * Math.max(.4, 1 - 116 / Math.max(1, this.host.clientHeight));
    const height = Math.max(2, ...this.data.assets.filter(a => !room || a.room_id === room.id).map(a => a.world.y + 2));
    let distance = this.controls.minDistance;
    // Fit the projected corners, including rotation and equipment height, on narrow screens.
    for (const x of [box.min.x, box.max.x]) for (const y of [0, height]) for (const z of [box.min.z, box.max.z]) {
      const point = new THREE.Vector3(x, y, z).sub(center);
      distance = Math.max(distance, point.dot(offset) + Math.abs(point.dot(right)) / horizontal,
        point.dot(offset) + Math.abs(point.dot(up)) / vertical);
    }
    distance += 1;
    this.controls.maxDistance = Math.max(95, distance * 1.5);
    this.camera.far = Math.max(300, this.controls.maxDistance * 3);
    this.camera.updateProjectionMatrix();
    const position = center.clone().add(offset.multiplyScalar(distance));
    this.transition = { start: performance.now(), from: this.camera.position.clone(), targetFrom: this.controls.target.clone(), position, center };
    if (immediate || matchMedia('(prefers-reduced-motion: reduce)').matches) {
      this.camera.position.copy(position); this.controls.target.copy(center); this.transition = null;
    }
    this.invalidate();
  }

  setInteractive(enabled) {
    if (this.controlsConnected !== enabled) {
      if (enabled) this.controls.connect(this.renderer.domElement);
      else this.controls.disconnect();
      this.controlsConnected = enabled;
    }
    this.controls.enabled = enabled;
    this.host.style.touchAction = enabled ? 'none' : 'pan-y pinch-zoom';
    this.renderer.domElement.style.touchAction = this.host.style.touchAction;
    if (!enabled) { this.pointers.clear(); this.down = null; this.multiTouch = false; }
  }

  zoom(factor) { this.transition = null; const delta = this.camera.position.clone().sub(this.controls.target); delta.setLength(THREE.MathUtils.clamp(delta.length() * factor, this.controls.minDistance, this.controls.maxDistance)); this.camera.position.copy(this.controls.target).add(delta); this.invalidate(); }

  invalidate() { if (!this.disposed && !this.contextLost && !this.frame) this.frame = requestAnimationFrame(time => this.render(time)); }

  render(time) {
    this.frame = null;
    if (this.disposed || this.contextLost) return;
    if (this.transition) {
      const t = Math.min((time - this.transition.start) / 650, 1), ease = 1 - Math.pow(1 - t, 3);
      this.camera.position.lerpVectors(this.transition.from, this.transition.position, ease);
      this.controls.target.lerpVectors(this.transition.targetFrom, this.transition.center, ease);
      if (t === 1) this.transition = null;
    }
    const changed = this.controls.update();
    this.renderer.render(this.scene, this.camera);
    for (const label of this.labelItems || []) {
      const p = label.position.clone().project(this.camera);
      const x = (p.x + 1) / 2 * this.host.clientWidth, y = (1 - p.y) / 2 * this.host.clientHeight;
      const radius = (label.element.offsetWidth || 44) / 2 + 4;
      const hidden = x < radius || x > this.host.clientWidth - radius || y < radius || y > this.host.clientHeight - radius || p.z > 1 || p.z < -1 || this.selectedRoom;
      label.element.hidden = !!hidden;
      label.element.style.transform = `translate(-50%, -50%) translate(${x}px, ${y}px)`;
    }
    if (this.transition || changed) this.invalidate();
  }

  dispose() { this.disposed = true; cancelAnimationFrame(this.frame); this.abort.abort(); this.resize.disconnect(); this.controls.dispose(); this.clear(); this.renderer.dispose(); this.renderer.forceContextLoss(); this.renderer.domElement.remove(); }
}
