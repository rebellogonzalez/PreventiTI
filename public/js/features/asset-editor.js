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
  const inputs = Object.fromEntries(['categoria_id', 'ubicacion_id', 'nombre', 'serie', 'responsable', 'frecuencia_service']
    .map(name => [name, form.elements.namedItem(name)]));
  let editingId = null;
  let version = null;
  let saving = false;
  let generation = 0;
  const canEdit = () => !!window.currentUserProfile?.permissions?.can_manage_assets;

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

  function populateChoices(asset = null) {
    inputs.categoria_id.replaceChildren(...db.categories.map(item => new Option(item.name, item.id)));
    inputs.ubicacion_id.replaceChildren(...db.locations.map(item => new Option(item.name, item.id)));
    inputs.ubicacion_id.required = !asset || asset.ubicacion_id != null;
    if (asset) {
      inputs.categoria_id.value = String(asset.categoria_id);
      if (asset.ubicacion_id == null) {
        const label = asset.ubicacion_detalle?.nombre || asset.ubicacion || 'Sin ubicación registrada';
        inputs.ubicacion_id.prepend(new Option(`Conservar ubicación actual: ${label}`, ''));
      }
      inputs.ubicacion_id.value = asset.ubicacion_id == null ? '' : String(asset.ubicacion_id);
    }
  }

  window.openCreateAsset = function () {
    if (!canEdit() || saving) return;
    generation++;
    editingId = null;
    version = null;
    form.reset();
    clearErrors();
    populateChoices();
    title.textContent = 'Incorporar activo al inventario';
    status.textContent = 'Los campos marcados con * son obligatorios.';
    fields.disabled = false;
    submit.disabled = false;
    submit.textContent = 'Guardar en inventario';
    window.openModal(modalId);
    inputs.nombre.focus();
  };

  window.openEditAsset = async function (assetId) {
    if (!canEdit() || saving) return;
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
      inputs.responsable.value = asset.responsable === 'No asignado' ? '' : asset.responsable;
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

  reload.addEventListener('click', () => window.openEditAsset(editingId));
  form.addEventListener('input', event => event.target.removeAttribute('aria-invalid'));
  form.addEventListener('submit', async event => {
    event.preventDefault();
    if (!canEdit() || saving || (editingId !== null && !version) || !form.reportValidity()) return;
    const data = {
      categoria_id: Number(inputs.categoria_id.value),
      ubicacion_id: inputs.ubicacion_id.value ? Number(inputs.ubicacion_id.value) : null,
      nombre: inputs.nombre.value.trim(),
      serie: inputs.serie.value.trim(),
      responsable: inputs.responsable.value.trim() || null,
      frecuencia_service: Number(inputs.frecuencia_service.value),
    };
    if (editingId !== null) data.expected_version = version;
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
      const index = db.assets.findIndex(asset => asset.id === response.data.id);
      if (index < 0) db.assets.unshift(response.data);
      else db.assets[index] = response.data;
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
