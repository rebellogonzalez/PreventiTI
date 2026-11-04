const dateValue = value => String(value || '').replace('T', ' ');
const sameId = (left, right) => left != null && right != null && String(left) === String(right);

function newestAssignmentFirst(left, right) {
  return dateValue(right.assigned_at).localeCompare(dateValue(left.assigned_at))
    || Number(!right.finished_at) - Number(!left.finished_at)
    || dateValue(right.finished_at).localeCompare(dateValue(left.finished_at));
}

function responsibleTechnician(report, assignments) {
  if (report.assigned_technician?.id != null) return report.assigned_technician.id;

  const candidates = assignments.filter(assignment => !assignment.finished_at);
  const ordered = (candidates.length ? candidates : report.status === 'Finalizado' ? assignments : [])
    .slice().sort(newestAssignmentFirst);
  const latest = ordered[0];
  if (!latest || latest.reassignment_reason) return null;
  // An ambiguous legacy history cannot reliably credit a resolution to one person.
  if (ordered.some(item => newestAssignmentFirst(item, latest) === 0 && !sameId(item.technician_id, latest.technician_id))) return null;
  return latest.technician_id;
}

export function buildTechnicianHistories(technicians, reports) {
  const specialists = new Map(technicians
    .filter(technician => technician.role !== 'tecnico_lider')
    .map(technician => [String(technician.id), { ...technician, reports: [] }]));

  for (const report of new Map(reports.map(item => [String(item.id), item])).values()) {
    const assignments = report.assignment_history || [];
    const responsibleId = responsibleTechnician(report, assignments);
    const involvedIds = new Set(assignments.map(item => String(item.technician_id)));
    if (report.assigned_technician?.id != null) involvedIds.add(String(report.assigned_technician.id));

    for (const id of involvedIds) {
      const technician = specialists.get(id);
      if (!technician) continue;
      const periods = assignments.filter(item => sameId(item.technician_id, id)).slice();
      if (sameId(report.assigned_technician?.id, id) && !periods.some(item => !item.finished_at)) {
        periods.push({
          technician_id: report.assigned_technician.id,
          assigned_at: report.assigned_technician.assigned_at || null,
          finished_at: null,
          reassignment_reason: null,
        });
      }
      periods.sort(newestAssignmentFirst);
      const isResponsible = sameId(responsibleId, id);
      technician.reports.push({
        report,
        assignments: periods,
        lastAssignedAt: periods[0]?.assigned_at || null,
        participation: isResponsible ? (report.status === 'Finalizado' ? 'solved' : 'current') : 'previous',
      });
    }
  }

  return [...specialists.values()].map(technician => ({
    ...technician,
    reports: technician.reports.sort((left, right) =>
      dateValue(right.lastAssignedAt).localeCompare(dateValue(left.lastAssignedAt)) || Number(right.report.id) - Number(left.report.id)),
    assignedCount: technician.reports.length,
    activeCount: technician.reports.filter(item => item.participation === 'current').length,
    solvedCount: technician.reports.filter(item => item.participation === 'solved').length,
  }));
}
