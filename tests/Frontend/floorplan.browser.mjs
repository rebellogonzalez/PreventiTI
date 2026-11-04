import { mountFloorplan } from '../../public/js/floorplan/index.js';

const browserErrors = [];
window.addEventListener('error', event => { if (event.error) browserErrors.push(event.message); });
window.addEventListener('unhandledrejection', event => browserErrors.push(String(event.reason)));

const rooms = [
  { id: 'operations', label: 'Operaciones y atención administrativa', x: 0, z: 0, width: 15, depth: 10, color: '#e0e9e7' },
  { id: 'technical', label: 'Sala de soporte técnico y mantenimiento de dispositivos', x: 17, z: 0, width: 10, depth: 7, color: '#e0e8f1' },
  { id: 'management', label: 'Gerencia y coordinación', x: 17, z: 7, width: 10, depth: 7, color: '#ece5db' },
  { id: 'admin', label: 'Administración y recursos humanos', x: 0, z: 12, width: 15, depth: 7, color: '#ece5db' },
];
rooms.forEach(room => { room.floor = 'Planta principal'; room.location = room.label; });
const data = {
  rooms, can_edit_positions: false, updated_at: '2026-11-03T12:00:00Z',
  assets: Array.from({ length: 32 }, (_, i) => {
    const room = rooms[i < 17 ? 0 : i < 23 ? 1 : i < 27 ? 2 : 3];
    return { id: i + 1, code: `ACT-${String(i + 1).padStart(3, '0')}`, name: `Terminal de gestión administrativa y atención al público ${i + 1}`,
      room_id: room.id, category_id: 1, category_name: 'Terminales', operational_status: i === 2 ? 'incident' : 'operational', active_incidents: i === 2 ? 1 : 0,
      responsible: 'Personal administrativo', location_name: room.label, state: 'Operativo', position: null, reports: [], can_view_detail: false };
  }),
};
window.PreventiTIAPI = { request: async () => ({ data: structuredClone(data) }) };
const view = mountFloorplan(document.querySelector('#demo'));
const root = view.root;
const frame = () => new Promise(resolve => requestAnimationFrame(resolve));
const assert = (value, message) => { if (!value) throw new Error(message); };
for (let i = 0; i < 120 && view.busy; i++) await frame();
const tests = [];
const test = (name, run) => tests.push({ name, run });
const context = () => root.querySelector('.fp-context');
const isVisible = element => element.getClientRects().length > 0;
const reset = async () => { view.selectRoom(null); view.scene.focus(null, false, true); await frame(); };

test('Vista general: marcadores pequeños y nombres accesibles fuera del dibujo', async () => {
  assert(view.scene, 'WebGL no se inició');
  await reset();
  const labels = [...root.querySelectorAll('.fp-room-label')];
  assert(labels.length === rooms.length && labels.every(isVisible), 'Faltan salas en la vista general');
  for (const [i, label] of labels.entries()) {
    assert(label.textContent === String(i + 1).padStart(2, '0'), 'El marcador contiene una descripción extensa');
    assert(label.getBoundingClientRect().width <= 44.1, 'El marcador ocupa demasiado espacio');
    assert(label.getAttribute('aria-label').includes(rooms[i].label), 'Falta el nombre accesible');
    assert(root.querySelector(`[data-room-id="${rooms[i].id}"]`).textContent.includes(rooms[i].label), 'Falta el nombre en el explorador');
  }
});
test('Teclado: el foco muestra el nombre completo y entrar a una sala conserva la navegación', async () => {
  const label = root.querySelector('[data-room="technical"]');
  label.focus();
  assert(context().textContent.includes(rooms[1].label), 'El foco no identifica la sala');
  const descriptionRect = context().getBoundingClientRect(), stageRect = root.querySelector('.fp-stage').getBoundingClientRect();
  assert(descriptionRect.bottom <= stageRect.top || descriptionRect.top >= stageRect.bottom, 'El nombre se superpone al plano');
  label.click();
  await frame();
  assert(document.activeElement === view.scene.renderer.domElement, 'El foco quedó en un marcador oculto');
  assert(![...root.querySelectorAll('.fp-room-label')].some(isVisible), 'La sala conserva etiquetas superpuestas');
  assert(!root.querySelector('.fp-device-label'), 'Se crearon etiquetas para todos los equipos');
  assert(context().textContent.includes(rooms[1].label), 'Se perdió el nombre de la sala seleccionada');
  assert([...view.scene.devices.values()].filter(g => g.visible).length === 6, 'Se ocultaron equipos de la sala');
});
let pointer;
test('Señalar un dispositivo 3D muestra su nombre sin un cartel sobre el mapa', async () => {
  view.selectRoom('operations');
  view.scene.focus('operations', true, true);
  await frame();
  const scene = view.scene, rect = scene.renderer.domElement.getBoundingClientRect();
  const point = scene.devices.get(3).position.clone();
  point.y += .8;
  point.project(scene.camera);
  pointer = { clientX: rect.left + (point.x + 1) * rect.width / 2, clientY: rect.top + (1 - point.y) * rect.height / 2, pointerId: 1, bubbles: true };
  assert(scene.pick(pointer)?.assetId === 3, 'El dispositivo dejó de ser seleccionable en el plano');
  scene.renderer.domElement.dispatchEvent(new PointerEvent('pointermove', pointer));
  assert(context().textContent.includes(data.assets[2].name) && context().textContent.includes('Incidencia'), 'No se identifica el equipo o su estado');
  assert(!root.querySelector('.fp-tooltip'), 'Sigue apareciendo un cartel flotante');
  scene.renderer.domElement.dispatchEvent(new PointerEvent('pointerleave'));
  assert(context().textContent.includes(rooms[0].label) && !context().textContent.includes('ACT-003'), 'Quedó una descripción obsoleta al salir');
});
test('Seleccionar el dispositivo mantiene el detalle, el estado y el indicador en el plano', () => {
  const canvas = view.scene.renderer.domElement;
  const enabled = view.scene.controls.enabled;
  view.scene.setInteractive(false);
  try {
    canvas.dispatchEvent(new PointerEvent('pointerdown', { ...pointer, pointerType: 'touch', button: 0 }));
    canvas.dispatchEvent(new PointerEvent('pointerup', { ...pointer, pointerType: 'touch', button: 0 }));
  } finally { view.scene.setInteractive(enabled); }
  canvas.dispatchEvent(new PointerEvent('pointerleave'));
  assert(view.selectedAsset === 3 && view.scene.selection.visible, 'No se seleccionó el equipo');
  assert(root.querySelector('.fp-asset-heading').textContent.includes('ACT-003'), 'Falta la ficha lateral');
  assert(context().textContent.includes(data.assets[2].name), 'No se conserva el nombre seleccionado');
});
test('Filtros y regreso a la vista general limpian las descripciones anteriores', async () => {
  const input = root.querySelector('[data-filter="query"]');
  input.value = 'ACT-002'; input.dispatchEvent(new Event('input', { bubbles: true }));
  assert(view.selectedAsset === null && !view.scene.selection.visible, 'Persistió una selección filtrada');
  assert([...view.scene.devices.values()].filter(g => g.visible).length === 1, 'El filtro no controla los dispositivos visibles');
  assert(!context().textContent.includes('ACT-003'), 'Persistió el nombre filtrado');
  root.querySelector('[data-action="clear"]').click();
  view.scene.focus(null, false, true); await frame();
  assert([...root.querySelectorAll('.fp-room-label')].every(isVisible), 'No reaparecieron los marcadores');
  assert([...view.scene.devices.values()].filter(g => g.visible).length === data.assets.length, 'No se restauró el inventario');
});
test('Los nombres completos también se consultan desde la lista con teclado y se escapan', async () => {
  const row = root.querySelector('[data-asset-id="1"]');
  row.focus();
  assert(context().textContent.includes(data.assets[0].name), 'El foco en la lista no identifica el equipo');
  row.blur();
  data.assets[0].name = 'Terminal <img src=x onerror=alert(1)>';
  await view.refresh();
  view.selectAsset(1);
  assert(context().textContent.includes('<img') && !context().querySelector('img'), 'El nombre fue interpretado como HTML');
  assert(![...root.querySelectorAll('.fp-room-label')].some(isVisible), 'La sincronización volvió a superponer etiquetas');
});

test('Un arrastre que vuelve al punto inicial no selecciona un dispositivo', async () => {
  await reset();
  const scene = view.scene, canvas = scene.renderer.domElement, pick = scene.pick, enabled = scene.controls.enabled;
  scene.setInteractive(false);
  scene.pick = () => ({ assetId: 3, roomId: 'operations' });
  try {
    const point = { clientX: 100, clientY: 100, pointerId: 20, pointerType: 'touch', button: 0 };
    canvas.dispatchEvent(new PointerEvent('pointerdown', point));
    canvas.dispatchEvent(new PointerEvent('pointermove', { ...point, clientY: 160 }));
    canvas.dispatchEvent(new PointerEvent('pointermove', point));
    canvas.dispatchEvent(new PointerEvent('pointerup', point));
    assert(!view.selectedAsset && !view.filters.room, 'El arrastre se interpretó como un toque');
  } finally { scene.pick = pick; scene.setInteractive(enabled); }
});
test('Los gestos táctiles no cambian la descripción ni desplazan el plano durante el arrastre', async () => {
  await reset();
  const canvas = view.scene.renderer.domElement, pick = view.scene.pick;
  const before = context().textContent;
  view.scene.pick = () => ({ assetId: 3, roomId: 'operations' });
  try {
    canvas.dispatchEvent(new PointerEvent('pointermove', { pointerType: 'touch', pointerId: 21 }));
    assert(context().textContent === before, 'Se mostró una descripción al deslizar el dedo');
  } finally { view.scene.pick = pick; }
});
test('Una actualización sin cambios conserva las opciones del selector abierto', async () => {
  const category = root.querySelector('[data-filter="category"]'), room = root.querySelector('[data-filter="room"]');
  const option = category.options[1], roomOption = room.options[1];
  await view.refresh();
  assert(category.options[1] === option && room.options[1] === roomOption, 'Se reconstruyeron los selectores sin cambios');
});
test('La sincronización conserva coordenadas sin guardar aunque el teclado ya esté cerrado', async () => {
  data.can_edit_positions = true;
  await view.refresh(); view.selectAsset(1);
  const form = root.querySelector('.fp-position-form'), input = form.elements.x;
  form.closest('details').open = true;
  input.value = '44'; input.dispatchEvent(new Event('input', { bubbles: true }));
  input.blur();
  data.assets[1].responsible = 'Responsable actualizado';
  await view.refresh();
  assert(root.querySelector('.fp-position-form') === form && form.elements.x.value === '44', 'Se perdieron los cambios sin guardar');
  assert(form.closest('details').open, 'Se cerró el editor');
});
test('Un activo que cambia de sala sigue seleccionado en su ubicación actual', async () => {
  view.selectAsset(2);
  data.assets[1].room_id = 'technical';
  await view.refresh();
  assert(view.selectedAsset === 2 && view.filters.room === 'technical', 'La selección quedó en una sala anterior');
  data.assets[1].room_id = 'operations';
  await view.refresh();
});
test('Las categorías eliminadas no dejan filtros invisibles que vacían el mapa', async () => {
  view.selectRoom(null);
  view.filters.category = '1'; view.update();
  data.assets.forEach(asset => { asset.category_id = 2; });
  await view.refresh();
  assert(view.filters.category === '' && [...view.scene.devices.values()].every(group => group.visible), 'Quedó un filtro obsoleto');
  data.assets.forEach(asset => { asset.category_id = 1; });
  await view.refresh();
});
test('Recupera el mapa tras perder WebGL y conserva la sala seleccionada', async () => {
  view.selectRoom('technical');
  const previous = view.scene;
  previous.renderer.domElement.dispatchEvent(new Event('webglcontextlost', { cancelable: true }));
  assert(!root.querySelector('.fp-loading').hidden, 'Falta el aviso de pérdida del mapa');
  await view.refresh();
  assert(view.scene !== previous && !view.scene.contextLost, 'Actualizar no reconstruyó el mapa perdido');
  assert(root.querySelector('.fp-loading').hidden && view.filters.room === 'technical', 'No se recuperó la sala');
});
test('Cambiar el tamaño de pantalla conserva la vista superior', async () => {
  const scene = view.scene, host = scene.host;
  scene.focus('technical', true, true); await frame();
  host.style.right = '15px'; await frame(); await frame();
  try {
    const delta = scene.camera.position.clone().sub(scene.controls.target);
    assert(Math.abs(delta.x) < .01 && delta.y > Math.abs(delta.z) * 10, 'El cambio de tamaño volvió a la vista isométrica');
  } finally { host.style.right = ''; await frame(); }
});

test('Los controles móviles permiten bajar por la página y activar los gestos a voluntad', () => {
  if (!view.touchLayout.matches) return;
  const scene = view.scene, button = root.querySelector('[data-action="interact"]');
  assert(!scene.controls.enabled && scene.renderer.domElement.style.touchAction.includes('pan-y'), 'El mapa captura el desplazamiento inicial de la página');
  button.click();
  assert(scene.controls.enabled && button.getAttribute('aria-pressed') === 'true' && scene.renderer.domElement.style.touchAction === 'none', 'No se activan los gestos del mapa');
  button.click();
  assert(!scene.controls.enabled && button.getAttribute('aria-pressed') === 'false', 'No se puede volver al desplazamiento de la página');
  for (const control of root.querySelectorAll('.fp-stage-controls button, .fp-toolbar input, .fp-toolbar select')) {
    assert(control.getBoundingClientRect().height >= 44, 'Hay controles demasiado pequeños para tocar');
    if (control.matches('input,select')) assert(parseFloat(getComputedStyle(control).fontSize) >= 16, 'Un campo conserva texto demasiado pequeño');
  }
  if (view.mobileLayout.matches) {
    assert(getComputedStyle(root.querySelector('.fp-panel')).overflowY === 'visible', 'El detalle sigue atrapado en un panel con desplazamiento interno');
    assert(context().getBoundingClientRect().top >= root.querySelector('.fp-stage').getBoundingClientRect().bottom, 'La descripción móvil quedó fuera del alcance después del mapa');
  }
});
test('Cancelar un gesto o usar dos dedos no abre fichas accidentalmente', async () => {
  await reset();
  const scene = view.scene, canvas = scene.renderer.domElement, pick = scene.pick, enabled = scene.controls.enabled;
  scene.setInteractive(false); scene.pick = () => ({ assetId: 3, roomId: 'operations' });
  try {
    const point = { clientX: 100, clientY: 100, pointerId: 30, pointerType: 'touch', button: 0 };
    canvas.dispatchEvent(new PointerEvent('pointerdown', point));
    canvas.dispatchEvent(new PointerEvent('pointercancel', point));
    canvas.dispatchEvent(new PointerEvent('pointerup', point));
    assert(!view.selectedAsset, 'Un gesto cancelado abrió un dispositivo');
    canvas.dispatchEvent(new PointerEvent('pointerdown', point));
    canvas.dispatchEvent(new PointerEvent('pointerdown', { ...point, pointerId: 31 }));
    canvas.dispatchEvent(new PointerEvent('pointerup', point));
    canvas.dispatchEvent(new PointerEvent('pointerup', { ...point, pointerId: 31 }));
    assert(!view.selectedAsset, 'Un gesto de dos dedos abrió un dispositivo');
  } finally { scene.pick = pick; scene.setInteractive(enabled); }
});
test('Una caída de conexión conserva la última lectura y se recupera al reintentar', async () => {
  const request = window.PreventiTIAPI.request, scene = view.scene;
  try {
    window.PreventiTIAPI.request = async () => { throw new Error('Sin conexión de prueba'); };
    await view.refresh();
    assert(root.querySelector('.fp-sync').classList.contains('is-stale') && view.scene === scene && scene.devices.size === 32, 'Se perdió el inventario disponible');
  } finally { window.PreventiTIAPI.request = request; }
  await view.refresh();
  assert(!root.querySelector('.fp-sync').classList.contains('is-stale'), 'La conexión no se recuperó');
});
test('Guardar durante una lectura pendiente termina mostrando las coordenadas confirmadas', async () => {
  view.selectAsset(1);
  const form = root.querySelector('.fp-position-form');
  form.elements.x.value = '44'; form.dispatchEvent(new Event('input', { bubbles: true }));
  const request = window.PreventiTIAPI.request, toast = window.showToast;
  let release, reads = 0, writes = 0;
  const snapshot = structuredClone(data);
  window.showToast = () => {};
  window.PreventiTIAPI.request = async (_url, options) => {
    if (options?.method === 'PATCH') {
      writes++;
      const saved = JSON.parse(options.body);
      data.assets[0].position = { x: saved.x, z: saved.z, elevation: saved.elevation, rotation: saved.rotation, source: 'confirmed' };
      return { success: true };
    }
    if (++reads === 1) return new Promise(resolve => { release = () => resolve({ data: snapshot }); });
    return { data: structuredClone(data) };
  };
  try {
    const poll = view.refresh();
    const save = view.savePosition(form);
    await frame();
    assert(form.elements.x.disabled, 'Se puede editar el formulario durante el guardado');
    release(); await poll; await save;
    assert(writes === 1 && reads === 2 && view.data.assets.find(a => a.id === 1).position.x === .44, 'Una lectura anterior sobrescribió el guardado');
    const editor = root.querySelector('.fp-position-form');
    assert(editor.elements.x.value === '44.0' && !editor.elements.x.disabled, 'El editor no muestra el resultado confirmado');
  } finally { window.PreventiTIAPI.request = request; window.showToast = toast; }
});

test('Un dispositivo sin ubicación sigue disponible y explica cómo habilitar sus coordenadas', async () => {
  const room = { ...rooms[0], id: 'unlocated', floor: '', location: '', label: 'Sin ubicación registrada', x: 30 };
  rooms.push(room); data.assets[31].room_id = room.id;
  try {
    await view.refresh(); view.selectAsset(32);
    assert(root.querySelector('.fp-asset-heading').textContent.includes('ACT-032'), 'Se perdió el equipo sin ubicación');
    assert(!root.querySelector('.fp-position-form') && root.querySelector('.fp-position').textContent.includes('registra primero el piso y la sala'), 'Se ofrece un guardado que la API rechaza siempre');
  } finally { rooms.pop(); data.assets[31].room_id = 'admin'; await view.refresh(); }
});
test('Las interacciones no producen errores JavaScript sin controlar', () => {
  assert(browserErrors.length === 0, browserErrors.join('; '));
});

let passed = 0;
for (const { name, run } of tests) {
  const item = document.createElement('li');
  try { await run(); passed++; item.className = 'pass'; item.textContent = `✓ ${name}`; }
  catch (error) { item.className = 'fail'; item.textContent = `✗ ${name}: ${error.message}`; }
  document.querySelector('#results').appendChild(item);
}
document.querySelector('#summary').textContent = `${passed}/${tests.length} pruebas del mapa aprobadas · inventario ficticio`;
data.assets[0].name = 'Terminal de gestión administrativa y atención al público 1';
await view.refresh();
if (view.scene) await reset();
window.addEventListener('pagehide', () => view.dispose(), { once: true });
