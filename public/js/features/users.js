export function createUsers({ db }) {
  function renderUsuariosView(container) {
    const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' })[c]);
    container.innerHTML = `<div class="space-y-6"><div><h1 class="text-2xl font-bold">Usuarios y roles</h1><p class="text-sm text-slate-500 mt-2">${db.users.length} cuentas individuales. Cada acceso requiere correo y contraseña.</p></div>
      <div class="bg-white border rounded-xl p-5 text-sm space-y-2"><p><b>Administrador:</b> supervisión general, gestión patrimonial, directorio, planificación, costos e infraestructura.</p><p><b>Jefe técnico:</b> asignación y estados de reportes, alertas, planificación y coordinación técnica.</p><p><b>Técnico especialista:</b> inventario, mapa, mantenimiento, consumibles, agenda propia y reportes propios o asignados.</p><p><b>Empleado:</b> crear reportes y consultar exclusivamente sus propios reportes.</p></div>
      <div class="bg-white border rounded-xl overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-slate-50"><tr><th class="p-3">Nombre</th><th class="p-3">Correo</th><th class="p-3">Rol</th><th class="p-3">Estado</th></tr></thead><tbody>${db.users.map(u => `<tr class="border-t"><td class="p-3">${esc(u.name)}</td><td class="p-3">${esc(u.email)}</td><td class="p-3">${esc(u.role_title)}</td><td class="p-3">${u.active ? 'Activo' : 'Deshabilitado'}</td></tr>`).join('')}</tbody></table></div></div>`;
  }

  return { renderUsuariosView };
}
