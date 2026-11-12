import { escapeHTML } from '../lib/html.js';

export function openDialog(title) {
  const dialog = document.createElement('dialog');
  dialog.className = 'report-dialog';
  dialog.setAttribute('aria-label', title);
  dialog.innerHTML = `<div class="report-dialog-heading"><h2></h2>
    <button type="button" class="report-button report-button--secondary">Cerrar</button></div>
    <div class="report-dialog-body" aria-live="polite">Cargando…</div>`;
  dialog.querySelector('h2').textContent = title;
  dialog.querySelector('button').onclick = () => dialog.close();
  dialog.addEventListener('close', () => dialog.remove(), { once: true });
  document.body.append(dialog);
  dialog.showModal();
  return dialog;
}

export async function showReportDetail(id, renderCard) {
  const dialog = openDialog('Detalle del reporte');
  const body = dialog.querySelector('.report-dialog-body');
  try {
    const { data } = await window.PreventiTIAPI.request('/reportes/' + id);
    body.innerHTML = renderCard(data);
  } catch (error) {
    body.textContent = error.message;
  }
}

export async function editReport(id, onSaved) {
  const api = window.PreventiTIAPI;
  const canAssign = window.currentUserProfile.permissions.can_manage_reports;
  const dialog = openDialog('Gestionar reporte');
  const body = dialog.querySelector('.report-dialog-body');
  let report;
  let technicians = [];
  try {
    report = (await api.request('/reportes/' + id)).data;
    if (canAssign && !report.closed) technicians = (await api.reportTechnicians()).data;
  } catch (error) {
    body.textContent = error.message;
    return;
  }
  if (report.closed) {
    body.textContent = 'Este reporte ya está cerrado.';
    return;
  }
  if (!canAssign && !report.can_update_status) {
    body.textContent = 'No tienes permiso para cambiar el estado actual de este reporte.';
    return;
  }

  const current = report.assigned_technician;
  const starting = report.status === 'Recibido/Derivado';
  const actions = new Map();
  const available = starting ? technicians : technicians.filter(tech => tech.id !== current?.id);
  if (available.length) {
    const assignmentLabel = current ? 'Reasignar técnico' : 'Asignar técnico';
    actions.set('assign', starting ? 'Asignar técnico y pasar a En curso' : assignmentLabel);
  }
  if (report.can_update_status) {
    if (report.status === 'En curso') actions.set('Diagnóstico', 'Registrar diagnóstico');
    if (['En curso', 'Diagnóstico'].includes(report.status)) actions.set('Finalizado', 'Finalizar reporte');
  }
  body.innerHTML = `<p><strong>${escapeHTML(report.code)}</strong> · ${escapeHTML(report.status)}</p>
    <p>Técnico actual · ${escapeHTML(current?.name || 'Sin asignar')}</p>
    ${!actions.size ? '<p>Para continuar se necesita un especialista activo disponible.</p>' : `
    <form class="report-management-form">
      <label>Acción<select name="action">${[...actions].map(([value, label]) => `<option value="${value}">${label}</option>`).join('')}</select></label>
      <label>Técnico especialista<select name="technician" required><option value="">Seleccionar técnico</option>
        ${available.map(t => `<option value="${Number(t.id)}" ${t.id === current?.id ? 'selected' : ''}>${escapeHTML(t.name)}${t.specialty ? ' · ' + escapeHTML(t.specialty) : ''}</option>`).join('')}</select></label>
      <label>Motivo de la reasignación<textarea name="reason" rows="2" minlength="5" maxlength="2000" required></textarea></label>
      ${report.status === 'Diagnóstico' && report.action_report ? `<div class="report-existing-note"><strong>Accionar registrado en Diagnóstico</strong><p>${escapeHTML(report.action_report)}</p></div>` : ''}
      <label><span data-note-label>Informe de intervención</span><textarea name="note" rows="4" minlength="5" maxlength="5000"></textarea></label>
      <label>Diagnóstico del dispositivo<textarea name="diagnosis" rows="3" minlength="5" maxlength="5000" required></textarea></label>
      <p role="alert"></p><button type="submit" class="report-button report-button--primary">Guardar</button>
    </form>`}`;
  const form = body.querySelector('form');
  if (!form) return;
  const { action, technician, reason, note, diagnosis } = form.elements;
  const submit = form.querySelector('[type="submit"]');
  const feedback = form.querySelector('[role="alert"]');
  const setField = (field, visible) => {
    field.disabled = !visible;
    field.parentElement.hidden = !visible;
  };
  function selectAction() {
    const assigning = action.value === 'assign';
    const usePriorNote = action.value === 'Finalizado' && report.status === 'Diagnóstico' && report.action_report;
    setField(technician, assigning);
    setField(reason, assigning && !!current && Number(technician.value) !== current.id);
    setField(note, action.value === 'Finalizado' || action.value === 'Diagnóstico');
    setField(diagnosis, action.value === 'Diagnóstico');
    note.required = !note.disabled && !usePriorNote;
    form.querySelector('[data-note-label]').textContent = action.value === 'Finalizado' ? 'Informe de cierre' : 'Informe de intervención';
    note.placeholder = usePriorNote ? 'Opcional. Si lo dejas vacío, se conservará el accionar registrado.' : '';
    feedback.textContent = '';
  }
  action.addEventListener('change', selectAction);
  technician.addEventListener('change', selectAction);
  selectAction();
  form.addEventListener('submit', async event => {
    event.preventDefault();
    if (submit.disabled) return;
    submit.disabled = true;
    try {
      const response = action.value === 'assign'
        ? await api.assignReport(id, Number(technician.value), reason.disabled ? null : reason.value.trim(), report.version)
        : await api.transitionReport(id, action.value, note.disabled ? null : note.value.trim() || null, diagnosis.disabled ? null : diagnosis.value.trim(), report.version);
      if (dialog.open) dialog.close();
      onSaved(response.data);
      window.showToast('Reporte actualizado', response.message, 'success');
      api.reportSaved?.();
    } catch (error) {
      feedback.textContent = error.message;
      if (error.status === 409 || error.status === 403) {
        const reload = document.createElement('button');
        reload.type = 'button';
        reload.className = 'report-button report-button--secondary';
        reload.textContent = 'Revisar reporte actualizado';
        reload.onclick = () => { dialog.close(); editReport(id, onSaved); };
        form.append(reload);
      } else {
        submit.disabled = false;
      }
    }
  });
}
