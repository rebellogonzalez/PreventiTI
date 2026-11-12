/** Shared, side-effect-free helpers for the complete report history. */
(function (root) {
  'use strict';

  function normalize(value) {
    return String(value ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('es');
  }

  function dateKey(value) {
    return String(value ?? '').match(/^\d{4}-\d{2}-\d{2}(?=$|[ T])/)?.[0] || '';
  }

  function technicianFor(report) {
    if (report.assigned_technician) return report.assigned_technician;
    // Closed historical assignments still identify who handled a finished report.
    if (report.last_technician) return { ...report.last_technician, historical: true };
    if (!report.closed && !['Finalizado', 'Resuelto', 'Cerrado', 'Cancelado'].includes(report.status)) return null;
    const last = [...(report.assignment_history || [])].sort((a, b) =>
      String(b.assigned_at || '').localeCompare(String(a.assigned_at || ''))
    )[0];
    return last ? { id: last.technician_id, name: last.technician_name, historical: true } : null;
  }

  function assetKey(report) {
    return report.asset_id != null ? `id:${report.asset_id}` : `code:${report.asset_code || 'unknown'}`;
  }

  function selectReports(reports, filters = {}) {
    const terms = normalize(filters.query).trim().split(/\s+/).filter(Boolean);
    return reports.filter(report => {
      const technician = technicianFor(report);
      const date = dateKey(report.created_at);
      if (filters.status && (report.status || 'Sin estado registrado') !== filters.status) return false;
      if (filters.asset && assetKey(report) !== filters.asset) return false;
      if (filters.technician && (filters.technician === 'unassigned' ? technician != null : String(technician?.id) !== filters.technician)) return false;
      if (filters.from && (!date || date < filters.from)) return false;
      if (filters.to && (!date || date > filters.to)) return false;
      const searchable = normalize([
        report.id, report.code, report.asset_id, report.asset_code, report.asset_name,
        report.description, report.action_report, report.final_diagnosis, report.status,
        report.creator_name, report.created_at, technician?.id, technician?.name,
        ...(report.assignment_history || []).map(item => item.technician_name),
      ].join(' '));
      return terms.every(term => searchable.includes(term));
    }).sort((a, b) => {
      const aDate = dateKey(a.created_at);
      const bDate = dateKey(b.created_at);
      // Undated records remain accessible at the end in either direction.
      if (!aDate || !bDate) return aDate ? -1 : bDate ? 1 : Number(b.id) - Number(a.id);
      const order = String(a.created_at).replace('T', ' ').localeCompare(String(b.created_at).replace('T', ' ')) || Number(a.id) - Number(b.id);
      return filters.sort === 'oldest' ? order : -order;
    });
  }

  root.PreventiTIReportHistory = Object.freeze({ dateKey, technicianFor, assetKey, selectReports });
})(typeof window === 'undefined' ? globalThis : window);
