// Fechas civiles del calendario: hoy se determina en la zona del proyecto.
export const CALENDAR_TIME_ZONE = 'America/Montevideo';

export function todayDate(now = new Date()) {
  const parts = Object.fromEntries(new Intl.DateTimeFormat('en-CA', {
    timeZone: CALENDAR_TIME_ZONE, year: 'numeric', month: '2-digit', day: '2-digit',
  }).formatToParts(now).map(part => [part.type, part.value]));
  return `${parts.year}-${parts.month}-${parts.day}`;
}

export function isCalendarDate(value) {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(String(value))) return false;
  const date = new Date(`${value}T12:00:00Z`);
  return Number.isFinite(date.getTime()) && date.toISOString().slice(0, 10) === value;
}

function civilDate(value) {
  return new Date(`${value}T12:00:00Z`);
}

export function shiftDays(value, amount) {
  const date = civilDate(value);
  date.setUTCDate(date.getUTCDate() + amount);
  return date.toISOString().slice(0, 10);
}

export function shiftMonth(value, amount) {
  const date = civilDate(value);
  const day = date.getUTCDate();
  date.setUTCDate(1);
  date.setUTCMonth(date.getUTCMonth() + amount);
  const last = new Date(date);
  last.setUTCMonth(last.getUTCMonth() + 1, 0);
  date.setUTCDate(Math.min(day, last.getUTCDate()));
  return date.toISOString().slice(0, 10);
}

export function weekRange(value) {
  const mondayOffset = (civilDate(value).getUTCDay() + 6) % 7;
  const start = shiftDays(value, -mondayOffset);
  return { start, end: shiftDays(start, 6) };
}

export function monthCells(value) {
  const first = `${value.slice(0, 7)}-01`;
  const last = shiftDays(shiftMonth(first, 1), -1);
  const offset = (civilDate(first).getUTCDay() + 6) % 7;
  const total = Math.ceil((offset + Number(last.slice(-2))) / 7) * 7;
  return Array.from({ length: total }, (_, index) => {
    const date = shiftDays(first, index - offset);
    return { date, day: Number(date.slice(-2)), inMonth: date.slice(0, 7) === value.slice(0, 7) };
  });
}

export function calendarMetrics(tasks, selectedDate) {
  const week = weekRange(selectedDate);
  return {
    day: tasks.filter(task => task.date === selectedDate).length,
    week: tasks.filter(task => task.date >= week.start && task.date <= week.end).length,
    month: tasks.filter(task => String(task.date).slice(0, 7) === selectedDate.slice(0, 7)).length,
    weekStart: week.start,
    weekEnd: week.end,
  };
}

export function dayTasks(tasks, selectedDate, type = '') {
  return tasks.filter(task => task.date === selectedDate && (!type || task.type === type))
    .sort((left, right) => String(left.time || '99:99').localeCompare(String(right.time || '99:99')) || Number(left.id) - Number(right.id));
}

export function formatCalendarDate(value, options = { day: 'numeric', month: 'long', year: 'numeric' }) {
  return new Intl.DateTimeFormat('es-UY', { ...options, timeZone: 'UTC' }).format(civilDate(value));
}

export function monthLabel(value) {
  const label = formatCalendarDate(value, { month: 'long', year: 'numeric' });
  return label.charAt(0).toLocaleUpperCase('es') + label.slice(1);
}
