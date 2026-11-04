import { escapeHTML } from '../lib/html.js';
import { enhanceAssetSelect } from './asset-select.js';

export function createForms({ db, saveDB }) {
  function initFormHandlers() {
    // 1. Creación de reporte persistido
    const formNuevoReporte = document.getElementById('form-nuevo-reporte');
    if (formNuevoReporte) {
      formNuevoReporte.addEventListener('submit', async event => {
        event.preventDefault();

        const assetId = parseInt(document.getElementById('reporte-activo-select').value);
        const priority = document.getElementById('reporte-prioridad-select').value;
        const description = document.getElementById('reporte-descripcion-input').value.trim();
        const submitButton = formNuevoReporte.querySelector('button[type="submit"]');

        if (!assetId || !description) {
          window.showToast('Datos incompletos', 'Seleccione un activo y describa la situación.', 'warning');
          return;
        }

        submitButton.disabled = true;
        submitButton.textContent = 'Guardando…';

        try {
          const response = await window.PreventiTIAPI.createReport(assetId, priority, description);
          db.reports = db.reports || [];
          db.reports.unshift(response.data);

          if (window.currentUserProfile?.permissions?.can_view_notifications) {
            const notificationResponse = await window.PreventiTIAPI.request('/notificaciones');
            db.notifications = notificationResponse.data || [];
          }

          window.closeModal('modal-nuevo-reporte');
          formNuevoReporte.reset();
          saveDB();
          window.showToast('Reporte registrado', response.message, 'success');
        } catch (error) {
          window.showToast('No se pudo guardar el reporte', error.message, 'danger');
        } finally {
          submitButton.disabled = false;
          submitButton.textContent = 'Guardar reporte';
        }
      });
    }

    // 4. Form Registrar Mantenimiento Preventivo (Calculadora Determinista)
    const formNuevoMant = document.getElementById('form-registrar-mantenimiento');
    if (formNuevoMant) {
      const assetSelect = document.getElementById('mant-asset-select');
      const dateInput = document.getElementById('mant-date-input');
      const calcDisplay = document.getElementById('mant-calc-preview');

      function updateCalcPreview() {
        if (!assetSelect || !dateInput || !calcDisplay) return;
        const assetId = parseInt(assetSelect.value);
        const dateVal = dateInput.value;
        const asset = db.assets.find(a => a.id === assetId);

        if (asset && dateVal) {
          const mDate = new Date(dateVal);
          mDate.setDate(mDate.getDate() + asset.periodicity_days);
          const nextStr = mDate.toISOString().split('T')[0];
          calcDisplay.innerHTML = `
            <b>Cálculo Determinista:</b> ${dateVal} + <b>${asset.periodicity_days} días</b> (${asset.category_name}) &rarr; <span class="text-emerald-400 font-bold font-mono">${nextStr}</span>
          `;
        } else {
          calcDisplay.innerHTML = 'Seleccione un activo y fecha para proyectar la próxima revisión.';
        }
      }

      if (assetSelect) assetSelect.addEventListener('change', updateCalcPreview);
      if (dateInput) dateInput.addEventListener('input', updateCalcPreview);

      formNuevoMant.addEventListener('submit', async event => {
        event.preventDefault();
        const button = formNuevoMant.querySelector('button[type="submit"]');
        button.disabled = true;
        try {
          const response = await window.PreventiTIAPI.registerMaintenance(Number(assetSelect.value), dateInput.value, document.getElementById('mant-detail-input').value.trim());
          await window.PreventiTIAPI.syncWithBackend();
          window.closeModal('modal-registrar-mantenimiento');
          formNuevoMant.reset();
          window.showToast('Mantenimiento registrado', response.message, 'success');
        } catch (error) { window.showToast('No se pudo guardar', error.message, 'danger'); }
        finally { button.disabled = false; }
      });
    }

    // 5. Form Actualizar Consumible
    const formConsumable = document.getElementById('form-actualizar-consumible');
    if (formConsumable) {
      const slider = document.getElementById('consumible-level-slider');
      const disp = document.getElementById('consumible-level-display');
      if (slider && disp) {
        slider.addEventListener('input', (e) => {
          disp.textContent = `${e.target.value}%`;
        });
      }

      formConsumable.addEventListener('submit', async event => {
        event.preventDefault();
        const button = formConsumable.querySelector('button[type="submit"]');
        button.disabled = true;
        try {
          const response = await window.PreventiTIAPI.updateConsumableLevel(Number(document.getElementById('consumible-asset-id').value), Number(slider.value));
          await window.PreventiTIAPI.syncWithBackend();
          window.closeModal('modal-actualizar-consumible');
          window.showToast('Insumo actualizado', response.message, 'success');
        } catch (error) { window.showToast('No se pudo guardar', error.message, 'danger'); }
        finally { button.disabled = false; }
      });
    }

    // 7. Form Nueva Tarea en Calendario
    const formNuevaTarea = document.getElementById('form-nueva-tarea');
    if (formNuevaTarea) {
      formNuevaTarea.addEventListener('submit', async event => {
        event.preventDefault();
        const button = formNuevaTarea.querySelector('button[type="submit"]');
        button.disabled = true;
        try {
          const response = await window.PreventiTIAPI.request('/calendario', { method: 'POST', body: JSON.stringify({
            fecha: document.getElementById('tarea-fecha').value,
            hora: document.getElementById('tarea-hora').value,
            titulo: document.getElementById('tarea-titulo').value.trim(),
            tipo: document.getElementById('tarea-tipo').value,
            priority: document.getElementById('tarea-prioridad').value,
            technician_id: Number(document.getElementById('tarea-tecnico').value),
            asset_id: Number(document.getElementById('tarea-activo').value) || null,
          }) });
          await window.PreventiTIAPI.syncWithBackend();
          window.closeModal('modal-nueva-tarea-calendario');
          formNuevaTarea.reset();
          window.showToast('Tarea agendada', response.message, 'success');
        } catch (error) { window.showToast('No se pudo guardar', error.message, 'danger'); }
        finally { button.disabled = false; }
      });
    }
  }

  function populateModalSelectors() {
    // Selector de activos para crear un reporte
    const tAssetSel = document.getElementById('reporte-activo-select');
    if (tAssetSel) {
      enhanceAssetSelect(tAssetSel, db.reportAssets.filter(a => a.active), {
        label: 'Activo afectado',
        placeholder: 'Seleccione un dispositivo para reportar…',
      });
    }

    // Selector activos para Mantenimiento
    const mMantSel = document.getElementById('mant-asset-select');
    if (mMantSel) {
      enhanceAssetSelect(mMantSel, db.assets, {
        label: 'Activo intervenido',
        placeholder: 'Seleccione un dispositivo para el mantenimiento…',
      });
    }

    // Selectores para Nueva Tarea en Calendario
    const cTechSel = document.getElementById('tarea-tecnico');
    if (cTechSel) {
      cTechSel.innerHTML = db.taskAssignees.map(t => `<option value="${t.id}">${escapeHTML(t.name)} (${escapeHTML(t.role_title)})</option>`).join('');
    }
    const cAssetSel = document.getElementById('tarea-activo');
    if (cAssetSel) {
      enhanceAssetSelect(cAssetSel, db.assets, {
        label: 'Activo asociado a la tarea',
        emptyLabel: 'Ninguno / General',
        emptyOptionLabel: 'Ninguno / General',
        showEmptyOption: true,
        placeholder: 'Sin dispositivo asociado',
      });
    }


  }

  return { initFormHandlers, populateModalSelectors };
}
