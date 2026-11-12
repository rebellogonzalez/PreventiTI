// Only for timestamps that identify an instant. Scheduled task times are civil HH:MM values.
export function formatTimestamp(value, { timeZone, includeDate = true } = {}) {
  if (!value) return 'Sin fecha';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return 'Sin fecha';
  return new Intl.DateTimeFormat('es-UY', {
    timeZone, hourCycle: 'h23', hour: '2-digit', minute: '2-digit', second: '2-digit',
    ...(includeDate && { day: '2-digit', month: '2-digit', year: 'numeric' }),
  }).format(date);
}
