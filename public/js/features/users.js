import { escapeHTML } from '../lib/html.js';
import { normalizeSearch } from '../lib/search.js';

export function createUsers({ db }) {
  let query = '';
  function renderUsuariosView(container) {
    const active = db.users.filter(user => user.active).length;
    const devicesByUser = new Map();
    for (const asset of db.assets) {
      if (asset.responsible_id == null) continue;
      const owner = Number(asset.responsible_id);
      if (!devicesByUser.has(owner)) devicesByUser.set(owner, []);
      devicesByUser.get(owner).push(asset);
    }
    container.innerHTML = `
      <section class="user-directory space-y-5" aria-labelledby="users-title">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div><h1 id="users-title" class="text-2xl font-bold text-slate-900">Usuarios y roles</h1>
            <p class="mt-1 text-sm text-slate-500">${active} cuentas activas de ${db.users.length}</p></div>
          <button type="button" class="user-primary" data-open-user>Agregar usuario</button>
        </div>
        <details class="bg-white border rounded-xl p-4 text-sm text-slate-700">
          <summary class="font-semibold">Permisos por rol</summary>
          <dl class="user-roles">
            <dt>Administrador</dt><dd>Gestión de cuentas, inventario, planificación, costos e infraestructura.</dd>
            <dt>Jefe técnico</dt><dd>Gestión de cuentas, asignación y seguimiento de reportes, alertas y coordinación técnica.</dd>
            <dt>Técnico especialista</dt><dd>Inventario, mapa, mantenimiento, consumibles, agenda propia y consulta de reportes propios o asignados. Puede diagnosticar y finalizar los reportes que tiene asignados desde que pasan a En curso.</dd>
            <dt>Empleado</dt><dd>Creación y consulta de sus propios reportes.</dd>
          </dl>
        </details>
        <div class="bg-white border rounded-xl overflow-x-auto">
          <div class="p-3">
            <label for="users-search" class="block text-sm font-semibold text-slate-700 mb-1">Buscar por nombre o apellido</label>
            <input type="search" id="users-search" autocomplete="off" value="${escapeHTML(query)}"
              aria-controls="users-results" class="w-full py-2 px-3 text-sm border border-slate-300 rounded-lg" placeholder="Nombre o apellido">
          </div>
          <table class="w-full text-left text-sm"><thead class="bg-slate-50"><tr>
            <th class="p-3">Cuenta</th><th class="p-3">Rol</th><th class="p-3">Estado</th><th class="p-3">Dispositivos asignados</th><th class="p-3">Acciones</th>
          </tr></thead><tbody id="users-results">${db.users.map(user => `
            <tr class="border-t" data-user-name="${escapeHTML(normalizeSearch(user.name))}">
              <td class="p-3"><strong>${escapeHTML(user.name)}</strong><div class="text-xs text-slate-500">${escapeHTML(user.email)}</div></td>
              <td class="p-3">${escapeHTML(user.role_title)}</td><td class="p-3">${user.active ? 'Activa' : 'Deshabilitada'}</td>
              <td class="p-3 user-devices">${devicesByUser.has(Number(user.id))
                ? `<ul class="space-y-1">${devicesByUser.get(Number(user.id)).map(asset =>
                  `<li>${escapeHTML(asset.name)} <span class="text-xs text-slate-500">(${escapeHTML(asset.code)})</span></li>`).join('')}</ul>`
                : 'Sin dispositivos asignados'}</td>
              <td class="p-3">${user.id === db.currentUserId ? 'Tu cuenta' : user.active
                ? `<button type="button" class="user-remove" data-remove-user="${Number(user.id)}" aria-label="Dar de baja a ${escapeHTML(user.name)}">Dar de baja</button>`
                : '—'}</td>
            </tr>`).join('')}</tbody></table>
          <p data-search-empty role="status" class="p-3 text-sm text-slate-500" hidden>No se encontraron usuarios.</p>
        </div>
        <dialog class="user-create-dialog" aria-labelledby="create-user-title">
          <form class="user-create-form">
            <h2 id="create-user-title" class="font-bold text-lg">Agregar usuario</h2>
            <div class="user-fields">
              <label>Nombre<input name="nombre" required maxlength="100" autocomplete="off"></label>
              <label>Apellido (opcional)<input name="apellido" maxlength="100" autocomplete="off"></label>
              <label class="user-full-width">Correo de acceso<input name="email" type="email" required maxlength="150" autocomplete="off"></label>
              <label class="user-full-width">Contraseña inicial<input name="password" type="password" autocomplete="new-password" minlength="8" maxlength="72" required placeholder="Al menos 8 caracteres"></label>
              <label>Rol<select name="role">
                <option value="empleado">Empleado</option><option value="tecnico">Técnico especialista</option>
                <option value="tecnico_lider">Jefe técnico</option><option value="administrador">Administrador</option>
              </select></label>
              <label data-specialty hidden>Especialidad<select name="specialty" disabled>
                <option value="">Seleccionar…</option><option>Hardware</option><option>Software</option><option>Redes/Infraestructura</option>
              </select></label>
            </div>
            <p data-user-error role="alert" class="text-sm text-rose-700"></p>
            <div class="flex justify-end gap-2"><button type="button" class="px-3 py-2" data-cancel-user>Cancelar</button>
              <button type="submit" class="user-primary">Crear cuenta</button></div>
          </form>
        </dialog>
      </section>`;

    const search = container.querySelector('#users-search');
    const rows = container.querySelectorAll('[data-user-name]');
    function filterUsers() {
      query = search.value;
      const terms = normalizeSearch(query).split(/\s+/).filter(Boolean);
      let matches = 0;
      for (const row of rows) {
        row.hidden = !terms.every(term => row.dataset.userName.includes(term));
        if (!row.hidden) matches++;
      }
      container.querySelector('[data-search-empty]').hidden = matches > 0;
    }
    search.addEventListener('input', filterUsers);
    filterUsers();

    const dialog = container.querySelector('dialog');
    const form = dialog.querySelector('form');
    const errorLabel = form.querySelector('[data-user-error]');
    const submit = form.querySelector('[type="submit"]');
    const cancel = form.querySelector('[data-cancel-user]');
    function updateSpecialty() {
      const technical = ['tecnico', 'tecnico_lider'].includes(form.elements.role.value);
      form.querySelector('[data-specialty]').hidden = !technical;
      form.elements.specialty.disabled = !technical;
      form.elements.specialty.required = technical;
    }
    form.elements.role.addEventListener('change', updateSpecialty);
    container.querySelector('[data-open-user]').addEventListener('click', () => {
      form.reset();
      updateSpecialty();
      errorLabel.textContent = '';
      dialog.showModal();
    });
    cancel.addEventListener('click', () => dialog.close());
    dialog.addEventListener('cancel', event => { if (submit.disabled) event.preventDefault(); });
    dialog.addEventListener('close', () => form.reset());
    form.addEventListener('submit', async event => {
      event.preventDefault();
      if (submit.disabled) return;
      errorLabel.textContent = '';
      submit.disabled = cancel.disabled = true;
      try {
        await window.PreventiTIAPI.request('/auth/users', { method: 'POST', body: JSON.stringify(Object.fromEntries(new FormData(form))) });
        dialog.close();
        await refreshAccounts('Cuenta creada');
      } catch (error) {
        errorLabel.textContent = error.message;
      } finally {
        submit.disabled = cancel.disabled = false;
      }
    });

    container.querySelectorAll('[data-remove-user]').forEach(button => {
      button.addEventListener('click', async () => {
        const user = db.users.find(item => item.id === Number(button.dataset.removeUser));
        if (!user || !window.confirm(`¿Dar de baja a ${user.name} (${user.email})? Se cerrarán sus sesiones y sus dispositivos y trabajos pendientes quedarán disponibles para reasignación. Se conservará su historial.`)) return;
        button.disabled = true;
        try {
          const { data } = await window.PreventiTIAPI.request(`/auth/users/${user.id}`, { method: 'DELETE' });
          const changes = [];
          if (data?.tasks) changes.push(`Tareas por asignar: ${data.tasks}`);
          if (data?.reports) changes.push(`Reportes por asignar: ${data.reports}`);
          if (data?.assets) changes.push(`Dispositivos sin responsable: ${data.assets}`);
          if (data?.chief_reports) {
            const chief = db.users.find(person => person.id === data.next_chief_id);
            changes.push(data.next_chief_id
              ? `Reportes a cargo de ${chief?.name || 'la nueva jefatura'}: ${data.chief_reports}`
              : `Reportes que recibirá el próximo jefe técnico: ${data.chief_reports}`);
          }
          await refreshAccounts('Cuenta deshabilitada', changes.join('. '));
        } catch (error) {
          window.showToast('No se pudo dar de baja', error.message, 'danger');
        } finally {
          button.disabled = false;
        }
      });
    });
  }

  async function refreshAccounts(title, detail) {
    try {
      await window.PreventiTIAPI.syncWithBackend();
      window.showToast(title, detail || 'El directorio está actualizado.', 'success');
    } catch {
      window.showToast(title, 'El cambio se guardó. Recarga la página para actualizar el directorio.', 'warning');
    }
  }

  return { renderUsuariosView };
}
