import { escapeHTML } from '../lib/html.js';
import { buildTechnicianHistories } from '../lib/technician-history.js';

function formatDate(value) {
  const parts = String(value || '').match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}:\d{2}))?/);
  return parts ? `${parts[3]}/${parts[2]}/${parts[1]}${parts[4] ? ` · ${parts[4]}` : ''}` : 'Sin fecha registrada';
}

function renderReport(entry) {
  const { report, assignments, participation } = entry;
  const label = { solved: 'Solucionado', current: 'Asignado actualmente', previous: 'Participación anterior' }[participation];
  const reportId = Number(report.id);
  return `
    <tr>
      <td>
        <strong>${escapeHTML(report.code || `Reporte #${report.id}`)}</strong>
        <span class="technician-history-description">${escapeHTML(report.description || 'Sin descripción registrada')}</span>
        ${Number.isSafeInteger(reportId) && reportId > 0 ? `<button type="button" class="technician-history-link" onclick="window.viewReportDetail(${reportId})">Ver reporte<span class="sr-only"> ${escapeHTML(report.code || report.id)}</span></button>` : ''}
      </td>
      <td>${escapeHTML(report.asset_name || 'Activo sin referencia actual')}<span class="technician-history-meta">${escapeHTML(report.asset_code || 'Sin código registrado')}</span></td>
      <td>
        ${escapeHTML(formatDate(entry.lastAssignedAt))}
        <details class="technician-assignment-periods">
          <summary>Ver asignaciones (${assignments.length})</summary>
          <ol>${assignments.map(period => `
            <li><strong>${escapeHTML(formatDate(period.assigned_at))}</strong>
              <span>${period.finished_at ? `Fin de asignación: ${escapeHTML(formatDate(period.finished_at))}` : participation === 'solved' ? `Cierre del reporte: ${escapeHTML(formatDate(report.closed_at))}` : participation === 'current' ? 'Asignación vigente' : 'Sin fecha de fin registrada'}</span>
              ${period.reassignment_reason ? `<p>Reasignación: ${escapeHTML(period.reassignment_reason)}</p>` : ''}
            </li>`).join('')}</ol>
        </details>
      </td>
      <td><span class="technician-history-status is-${participation}">${label}</span><span class="technician-history-meta">Estado del reporte: ${escapeHTML(report.status || 'Sin estado registrado')}</span></td>
      <td>${report.status === 'Finalizado' ? escapeHTML(formatDate(report.closed_at)) : 'Pendiente de cierre'}
        ${participation === 'solved' ? `<span class="technician-history-meta">${escapeHTML(report.action_report || report.final_diagnosis || 'Sin resolución registrada')}</span>` : ''}
      </td>
    </tr>`;
}

export function renderTechnicianHistory(technicians, reports) {
  const histories = buildTechnicianHistories(technicians, reports);
  return `
    <section id="estadisticas-tecnicos" class="technician-history" aria-labelledby="technician-history-title">
      <div class="technician-history-heading">
        <div><h2 id="technician-history-title">Reportes por técnico</h2><p>Historial de asignaciones y soluciones de los técnicos especialistas.</p></div>
        <span>${histories.length} ${histories.length === 1 ? 'especialista' : 'especialistas'}</span>
      </div>
      <p class="technician-history-note">Cada reporte se cuenta una vez por técnico. Los solucionados se atribuyen al responsable al finalizar; las reasignaciones conservan su historial.</p>
      <div class="technician-history-list">
        ${histories.length ? histories.map(technician => `
          <details class="technician-history-card">
            <summary>
              <span class="technician-history-person"><strong>${escapeHTML(technician.name)}</strong><span>${escapeHTML(technician.specialty || 'Sin especialidad registrada')}${technician.active === false ? ' · Cuenta inactiva' : ''}</span><span class="technician-history-open-label">Ver historial</span></span>
              <span class="technician-history-counts">
                <span><strong>${technician.assignedCount}</strong><span>Asignados en total</span></span>
                <span><strong>${technician.activeCount}</strong><span>Actuales</span></span>
                <span class="technician-history-solved"><strong>${technician.solvedCount}</strong><span>Solucionados</span></span>
              </span>
            </summary>
            ${technician.reports.length ? `
              <div class="technician-history-table" tabindex="0" role="region" aria-label="Historial de ${escapeHTML(technician.name)}">
                <table>
                  <caption class="sr-only">Reportes asignados a ${escapeHTML(technician.name)}, ordenados por su última asignación</caption>
                  <thead><tr><th scope="col">Reporte</th><th scope="col">Dispositivo</th><th scope="col">Última asignación</th><th scope="col">Participación</th><th scope="col">Cierre del reporte</th></tr></thead>
                  <tbody>${technician.reports.map(renderReport).join('')}</tbody>
                </table>
              </div>` : '<p class="technician-history-empty">Este técnico todavía no tiene reportes asignados.</p>'}
          </details>`).join('') : '<p class="technician-history-empty">No hay técnicos especialistas registrados.</p>'}
      </div>
    </section>`;
}
