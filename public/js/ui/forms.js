import { escapeHTML } from '../lib/html.js';
import { todayDate, shiftDays } from '../lib/calendar-dates.js';
import { enhanceAssetSelect } from './asset-select.js';

export function initTaskTimeInput(input) {
  const template = '00:00';
  let previousValue = input.value;
  let selectionStart = 0;

  input.addEventListener('focus', () => {
    previousValue = input.value;
    input.select();
  });
  input.addEventListener('beforeinput', () => {
    previousValue = input.value;
    selectionStart = input.selectionStart;
  });
  input.addEventListener('input', event => {
    const raw = input.value;
    const caret = input.selectionStart;
    const deleting = event.inputType?.startsWith('delete');
    // Some mobile keyboards and paste operations provide the edit only in the field.
    const inserted = deleting ? '' : event.data ?? raw.slice(event.inputType ? selectionStart : 0, caret);
    let start = Math.max(0, caret - inserted.length);
    // The text after the caret still occupies the same slots at the end of the mask.
    let end = template.length - (raw.length - caret);
    if (deleting && start === 2 && end === 3) {
      if (event.inputType.endsWith('Backward')) start--;
      else end++;
    }

    let cursor = start;
    const slots = previousValue.split('');
    if (deleting || /^(?:\d{1,4}|\d{1,2}:\d{2})$/.test(inserted)) {
      for (let i = start; i < end; i++) slots[i] = template[i];
      const digits = inserted.replace(/^(\d):/, '0$1:').replace(':', '');
      for (const digit of digits) {
        if (cursor === 2) cursor++;
        if (cursor >= template.length) break;
        slots[cursor++] = digit;
      }
    }
    if (cursor === 2) cursor++;
    input.value = slots.join('');
    previousValue = input.value;
    input.setSelectionRange(cursor, cursor);
  });

  input.form.querySelectorAll('[data-time-step]').forEach(button => {
    button.addEventListener('click', () => {
      if (!input.reportValidity()) return;
      const [hours, minutes] = input.value.split(':').map(Number);
      const minutesPerDay = 24 * 60;
      const total = (hours * 60 + minutes + Number(button.dataset.timeStep) + minutesPerDay) % minutesPerDay;
      input.value = [Math.floor(total / 60), total % 60].map(part => String(part).padStart(2, '0')).join(':');
    });
  });
}

function submissionFor(previous, data) {
  const payload = JSON.stringify(data);
  if (previous?.payload === payload) return previous;
  const bytes = crypto.getRandomValues(new Uint8Array(16));
  bytes[6] = (bytes[6] & 15) | 64;
  bytes[8] = (bytes[8] & 63) | 128;
  const hex = Array.from(bytes, byte => byte.toString(16).padStart(2, '0')).join('');
  return { payload, id: hex.replace(/^(........)(....)(....)(....)(............)$/, '$1-$2-$3-$4-$5') };
}

async function refreshAfterSave() {
  try { await window.PreventiTIAPI.syncWithBackend(); }
  catch { window.showToast('Cambio guardado', 'Recarga la página para ver los datos actualizados.', 'warning'); }
}

function setOptions(select, label, items) {
  if (!select) return;
  const previous = select.value;
  select.replaceChildren(new Option(label, ''), ...items.map(([id, text]) => new Option(text, id)));
  if (items.some(([id]) => String(id) === previous)) select.value = previous;
}

export function createForms({ db, saveDB }) {
  function initFormHandlers() {
    // 1. Creación de reporte persistido
    const formNuevoReporte = document.getElementById('form-nuevo-reporte');
    if (formNuevoReporte) {
      let submission;
      formNuevoReporte.addEventListener('reset', () => { submission = null; });
      formNuevoReporte.addEventListener('submit', async event => {
        event.preventDefault();

        const assetId = parseInt(document.getElementById('reporte-activo-select').value);
        const priority = document.getElementById('reporte-prioridad-select').value;
        const description = document.getElementById('reporte-descripcion-input').value.trim();
        const submitButton = formNuevoReporte.querySelector('button[type="submit"]');

        if (submitButton.disabled) return;
        if (!assetId || !description) {
          window.showToast('Datos incompletos', 'Seleccione un activo y describa la situación.', 'warning');
          return;
        }

        submitButton.disabled = true;
        submitButton.textContent = 'Guardando…';

        submission = submissionFor(submission, [assetId, priority, description]);
        let response;
        try {
          response = await window.PreventiTIAPI.createReport(assetId, priority, description, submission.id);
        } catch (error) {
          window.showToast('No se pudo confirmar el envío', error.message, 'danger');
          return;
        } finally {
          submitButton.disabled = false;
          submitButton.textContent = 'Guardar reporte';
        }
        db.reports = [response.data, ...(db.reports || []).filter(item => item.id !== response.data.id)];
        formNuevoReporte.reset();
        window.closeModal('modal-nuevo-reporte');
        window.showToast('Reporte registrado', response.message, 'success');
        saveDB();
        window.PreventiTIAPI.reportSaved?.();
      });
    }

    // 4. Form Registrar Mantenimiento Preventivo (Calculadora Determinista)
    const formNuevoMant = document.getElementById('form-registrar-mantenimiento');
    if (formNuevoMant) {
      const assetSelect = document.getElementById('mant-asset-select');
      const dateInput = document.getElementById('mant-date-input');
      const calcDisplay = document.getElementById('mant-calc-preview');

      let submission;
      const taskSelect = document.getElementById('mant-task');
      const outcome = document.getElementById('mant-outcome');
      const reportSelect = document.getElementById('mant-report');
      const finding = document.getElementById('mant-finding');

      function updateInspectionFields() {
        const asset = db.assets.find(item => item.id === Number(assetSelect.value));
        dateInput.min = asset?.registered_on || '';
        dateInput.max = todayDate();
        if (!asset || !dateInput.value) calcDisplay.textContent = 'Seleccione un activo y la fecha de ejecución.';
        else if (asset.periodicity_days === 0) calcDisplay.textContent = 'Sin mantenimiento periódico.';
        else calcDisplay.textContent = `Próximo vencimiento proyectado: ${shiftDays(dateInput.value, asset.periodicity_days)} (${asset.periodicity_days} días).`;
        const tasks = (db.calendarTasks || []).filter(t => t.asset_id === asset?.id && t.type === 'mantenimiento' && t.technician_id && !t.completed && !t.cancel_reason);
        if (taskSelect) {
          setOptions(taskSelect, tasks.length ? 'Seleccione la visita o indique que es espontánea' : 'Inspección sin tarea programada',
            tasks.length ? [['standalone', 'Inspección espontánea'], ...tasks.map(t => [t.id, `${t.date} · ${t.time} · ${t.title}`])] : []);
          taskSelect.required = tasks.length > 0;
        }
        if (!outcome) return;
        setOptions(reportSelect, 'Seleccione un reporte', db.reports.filter(r => r.asset_id === asset?.id).map(r => [r.id, `${r.code} · ${r.description}`]));
        reportSelect.required = outcome.value === 'existing';
        finding.required = outcome.value === 'new';
        finding.disabled = !finding.required;
        reportSelect.disabled = !reportSelect.required;
        document.getElementById('mant-link-field').hidden = !reportSelect.required;
        document.getElementById('mant-finding-fields').hidden = !finding.required;
      }
      window.openInspection = function (taskId = null) {
        const task = (db.calendarTasks || []).find(item => item.id === taskId);
        formNuevoMant.reset();
        dateInput.value = todayDate();
        assetSelect.value = task?.asset_id || '';
        assetSelect.dispatchEvent(new Event('change', { bubbles: true }));
        if (taskSelect) {
          if (task) taskSelect.value = task.id;
          taskSelect.parentElement.hidden = Boolean(task);
          assetSelect.parentElement.closest('#form-registrar-mantenimiento > div').hidden = Boolean(task);
          const label = document.getElementById('mant-scheduled-context');
          label.hidden = !task;
          label.textContent = task ? `${task.asset} · ${task.date} · ${task.time} · ${task.title}` : '';
        }
        window.openModal('modal-registrar-mantenimiento');
      };
      document.getElementById('mant-schedule')?.addEventListener('click', () => {
        window.closeModal('modal-registrar-mantenimiento');
        window.openCalendarTaskModal(dateInput.value || todayDate(), assetSelect.value);
      });
      outcome?.addEventListener('change', updateInspectionFields);
      assetSelect.addEventListener('change', updateInspectionFields);
      dateInput.addEventListener('input', updateInspectionFields);
      formNuevoMant.addEventListener('reset', () => { submission = null; });

      formNuevoMant.addEventListener('submit', async event => {
        event.preventDefault();
        const button = formNuevoMant.querySelector('button[type="submit"]');
        if (button.disabled) return;
        button.disabled = true;
        try {
          const options = { task_id: Number(taskSelect?.value) || null };
          if (options.task_id) options.task_version = db.calendarTasks.find(t => t.id === options.task_id)?.version;
          if (outcome?.value === 'new') {
            options.finding = finding.value.trim();
            options.priority = document.getElementById('mant-priority').value;
          }
          if (outcome?.value === 'existing') options.report_id = Number(reportSelect.value);
          const detail = document.getElementById('mant-detail-input').value.trim();
          submission = submissionFor(submission, [assetSelect.value, dateInput.value, detail, options]);
          const response = await window.PreventiTIAPI.registerMaintenance(Number(assetSelect.value), dateInput.value, detail, { ...options, submission_id: submission.id });
          window.closeModal('modal-registrar-mantenimiento');
          formNuevoMant.reset();
          updateInspectionFields();
          window.showToast('Inspección registrada', response.message, 'success');
          await refreshAfterSave();
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
        if (button.disabled) return;
        button.disabled = true;
        try {
          const response = await window.PreventiTIAPI.updateConsumableLevel(Number(document.getElementById('consumible-asset-id').value), Number(slider.value), Number(document.getElementById('consumible-task')?.value) || null);
          window.closeModal('modal-actualizar-consumible');
          window.showToast('Insumo actualizado', response.message, 'success');
          await refreshAfterSave();
        } catch (error) { window.showToast('No se pudo guardar', error.message, 'danger'); }
        finally { button.disabled = false; }
      });
    }

    // 7. Form Nueva Tarea en Calendario
    const formNuevaTarea = document.getElementById('form-nueva-tarea');
    if (formNuevaTarea) {
      const taskTime = formNuevaTarea.querySelector('#tarea-hora');
      initTaskTimeInput(taskTime);
      let submission;
      const type = document.getElementById('tarea-tipo');
      const asset = document.getElementById('tarea-activo');
      const report = document.getElementById('calendar-report');
      function updateReportOptions() {
        if (!report) return;
        report.required = type.value === 'reporte';
        document.getElementById('calendar-report-field').hidden = !report.required;
        setOptions(report, 'Seleccione un reporte del dispositivo', db.reports.filter(r => r.asset_id === Number(asset.value)).map(r => [r.id, `${r.code} · ${r.description}`]));
      }
      type.addEventListener('change', updateReportOptions);
      asset.addEventListener('change', updateReportOptions);
      formNuevaTarea.addEventListener('reset', () => { submission = null; queueMicrotask(updateReportOptions); });

      formNuevaTarea.addEventListener('submit', async event => {
        event.preventDefault();
        const button = formNuevaTarea.querySelector('button[type="submit"]');
        if (button.disabled) return;
        button.disabled = true;
        try {
          const data = {
            fecha: document.getElementById('tarea-fecha').value,
            hora: taskTime.value,
            titulo: document.getElementById('tarea-titulo').value.trim(),
            tipo: document.getElementById('tarea-tipo').value,
            priority: document.getElementById('tarea-prioridad').value,
            technician_id: Number(document.getElementById('tarea-tecnico').value),
            asset_id: Number(document.getElementById('tarea-activo').value) || null,
            report_id: type.value === 'reporte' ? Number(report?.value) : null,
          };
          submission = submissionFor(submission, data);
          const response = await window.PreventiTIAPI.request('/calendario', { method: 'POST', body: JSON.stringify({ ...data, submission_id: submission.id }) });
          window.closeModal('modal-nueva-tarea-calendario');
          formNuevaTarea.reset();
          window.showToast('Tarea agendada', response.message, 'success');
          await refreshAfterSave();
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
        pickerDialog: document.getElementById('tarea-activo-dialog'),
        pickerTrigger: document.getElementById('tarea-activo-elegir'),
      });
    }


  }

  return { initFormHandlers, populateModalSelectors };
}
