import { enhanceOwnerSelect } from '../ui/asset-select.js';

export function initializeAssetEditor({ db, saveDB }) {
  const form = document.getElementById('form-nuevo-activo');
  if (!form) return;
  const modalId = 'modal-nuevo-activo';
  const modal = document.getElementById(modalId);
  const title = document.getElementById('asset-form-title');
  const errorBox = document.getElementById('asset-form-error');
  const status = document.getElementById('asset-form-status');
  const fields = document.getElementById('asset-form-fields');
  const reload = document.getElementById('asset-form-reload');
  const submit = form.querySelector('button[type="submit"]');
  const inputs = Object.fromEntries(['categoria_id', 'ubicacion_id', 'modelo', 'nombre', 'serie', 'responsable', 'responsable_id', 'frecuencia_service']
    .map(name => [name, form.elements.namedItem(name)]));
  let editingId = null;
  let version = null;
  let saving = false;
  let generation = 0;
  const permissions = () => window.currentUserProfile.permissions;

  function clearErrors() {
    errorBox.hidden = true;
    errorBox.textContent = '';
    reload.hidden = true;
    Object.values(inputs).forEach(input => input.removeAttribute('aria-invalid'));
  }

  function showError(error) {
    const messages = Object.values(error.errors || {}).flat();
    errorBox.textContent = messages.length ? messages.join('\n') : error.message;
    errorBox.hidden = false;
    Object.keys(error.errors || {}).forEach(name => inputs[name]?.setAttribute('aria-invalid', 'true'));
    reload.hidden = !editingId || (error.status !== 409 && version !== null);
    errorBox.focus();
  }

  function updateNameField() {
    const manual = editingId !== null || inputs.modelo.value === 'custom';
    document.getElementById('asset-name-field').hidden = !manual;
    inputs.nombre.disabled = !manual;
  }

  function updateModels() {
    if (editingId !== null) return;
    const category = inputs.categoria_id.value;
    const names = new Set(db.assets
      .filter(asset => String(asset.category_id) === category)
      .map(asset => asset.name));
    const models = [...names].sort((a, b) => a.localeCompare(b, 'es', { numeric: true }));
    inputs.modelo.replaceChildren(new Option(category ? 'Seleccionar modelo…' : 'Seleccionar primero el tipo…', ''),
      ...models.map((name, index) => new Option(name, String(index))),
      new Option('Ingresar otro modelo…', 'custom'));
    inputs.modelo.disabled = !category;
    if (category && !models.length) inputs.modelo.value = 'custom';
    updateNameField();
  }

  function populateChoices(asset = null) {
    document.getElementById('asset-state-field').hidden = !asset || window.PreventiTIInventory.isRetired({state: asset.estado});
    form.elements.estado_activo.value = '';
    if (asset) form.elements.estado_activo.options[0].textContent = `Conservar: ${asset.estado}`;
    const owners = db.users.filter(user => user.active || user.id === asset?.responsable_id);
    const freeText = asset?.responsable && !['No asignado', 'Sin asignar'].includes(asset.responsable);
    enhanceOwnerSelect(inputs.responsable_id, owners, asset?.responsable_id ?? (freeText ? 'other' : ''));
    inputs.responsable.hidden = inputs.responsable_id.value !== 'other';
    inputs.categoria_id.replaceChildren(new Option('Seleccionar tipo…', ''),
      ...db.categories.map(item => new Option(item.name, item.id)));
    inputs.ubicacion_id.replaceChildren(new Option('Seleccionar zona…', ''),
      ...db.locations.map(item => new Option(item.map_label, item.id)));
    inputs.ubicacion_id.required = !asset || asset.ubicacion_id != null;
    document.getElementById('asset-model-field').hidden = !!asset;
    if (asset) {
      inputs.modelo.disabled = true;
      updateNameField();
      inputs.categoria_id.value = String(asset.categoria_id);
      if (asset.ubicacion_id == null) {
        const label = asset.ubicacion_detalle?.nombre || asset.ubicacion || 'Sin ubicación registrada';
        inputs.ubicacion_id.options[0].textContent = `Conservar ubicación actual: ${label}`;
      }
      inputs.ubicacion_id.value = asset.ubicacion_id == null ? '' : String(asset.ubicacion_id);
    } else updateModels();
  }

  window.openCreateAsset = function (locationId = null) {
    if (!permissions().can_manage_assets || saving) return;
    generation++;
    editingId = null;
    version = null;
    form.reset();
    clearErrors();
    populateChoices();
    if (locationId != null) inputs.ubicacion_id.value = String(locationId);
    title.textContent = 'Incorporar activo al inventario';
    status.textContent = 'Los campos marcados con * son obligatorios.';
    fields.disabled = false;
    submit.disabled = false;
    submit.textContent = 'Guardar en inventario';
    window.openModal(modalId);
    inputs.categoria_id.focus();
  };

  window.openEditAsset = async function (assetId) {
    if (!permissions().can_edit_assets || saving) return;
    const currentGeneration = ++generation;
    editingId = Number(assetId);
    version = null;
    form.reset();
    clearErrors();
    title.textContent = 'Editar activo';
    status.textContent = 'Cargando datos actuales…';
    fields.disabled = true;
    submit.disabled = true;
    submit.textContent = 'Guardar cambios';
    window.closeModal('modal-detalle-activo');
    window.openModal(modalId);
    try {
      const response = await window.PreventiTIAPI.request(`/activos/${editingId}`);
      if (currentGeneration !== generation || modal.classList.contains('hidden')) return;
      const asset = response.data.asset;
      populateChoices(asset);
      inputs.nombre.value = asset.nombre;
      inputs.serie.value = asset.serie;
      inputs.responsable.value = inputs.responsable.hidden ? '' : asset.responsable;
      inputs.frecuencia_service.value = asset.frecuencia_service;
      version = asset.edit_version;
      title.textContent = `Editar ${asset.codigo}`;
      status.textContent = 'Los campos marcados con * son obligatorios.';
      fields.disabled = false;
      submit.disabled = false;
      inputs.nombre.focus();
    } catch (error) {
      if (currentGeneration !== generation || modal.classList.contains('hidden')) return;
      status.textContent = '';
      showError(error);
    }
  };

  inputs.categoria_id.addEventListener('change', updateModels);
  inputs.modelo.addEventListener('change', () => {
    updateNameField();
    if (!inputs.nombre.disabled) inputs.nombre.focus();
  });
  inputs.responsable_id.addEventListener('change', () => {
    inputs.responsable.hidden = inputs.responsable_id.value !== 'other';
    if (!inputs.responsable.hidden) inputs.responsable.focus();
  });
  reload.addEventListener('click', () => window.openEditAsset(editingId));
  form.addEventListener('input', event => event.target.removeAttribute('aria-invalid'));
  form.addEventListener('submit', async event => {
    event.preventDefault();
    const permitted = editingId === null ? permissions().can_manage_assets : permissions().can_edit_assets;
    if (!permitted || saving || (editingId !== null && !version) || !form.reportValidity()) return;
    const data = {
      categoria_id: Number(inputs.categoria_id.value),
      ubicacion_id: inputs.ubicacion_id.value ? Number(inputs.ubicacion_id.value) : null,
      nombre: inputs.nombre.disabled ? inputs.modelo.selectedOptions[0].text : inputs.nombre.value.trim(),
      serie: inputs.serie.value.trim(),
      responsable_id: inputs.responsable_id.value && inputs.responsable_id.value !== 'other' ? Number(inputs.responsable_id.value) : null,
      responsable: inputs.responsable_id.value === 'other' ? inputs.responsable.value.trim() || null : null,
      frecuencia_service: Number(inputs.frecuencia_service.value),
    };
    if (editingId !== null) {
      data.expected_version = version;
      if (form.elements.estado_activo.value) data.condicion_operativa = form.elements.estado_activo.value;
    }
    const isEditing = editingId !== null;
    clearErrors();
    saving = true;
    fields.disabled = true;
    submit.disabled = true;
    submit.textContent = 'Guardando…';
    status.textContent = '';
    try {
      const response = isEditing
        ? await window.PreventiTIAPI.updateAsset(editingId, data)
        : await window.PreventiTIAPI.createAsset(data);
      const list = window.PreventiTIInventory.isRetired(response.data) ? db.archivedAssets : db.assets;
      const index = list.findIndex(asset => asset.id === response.data.id);
      if (index < 0) list.unshift(response.data);
      else list[index] = response.data;
      window.closeModal(modalId);
      saveDB();
      window.showToast(isEditing ? 'Activo actualizado' : 'Activo creado', response.message, 'success');
      // Una falla de recarga posterior no convierte un guardado confirmado en fallo.
      try {
        await window.PreventiTIAPI.syncWithBackend();
      } catch (_) {
        window.showToast('Cambios guardados', 'Recarga la página para actualizar todos los datos del inventario.', 'warning');
      }
    } catch (error) {
      showError(error);
    } finally {
      saving = false;
      fields.disabled = false;
      submit.disabled = false;
      submit.textContent = isEditing ? 'Guardar cambios' : 'Guardar en inventario';
    }
  });
}
