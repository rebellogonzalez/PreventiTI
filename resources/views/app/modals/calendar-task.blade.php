  <!-- 8. Modal: Agendar Nueva Tarea en Calendario -->
  <div id="modal-nueva-tarea-calendario" class="fixed inset-0 z-50 modal-backdrop hidden items-center justify-center p-4">
    <div class="calendar-task-dialog bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200">
      <div class="flex items-center justify-between pb-3 border-b border-slate-100">
        <div class="flex items-center gap-2">
          <div class="p-2 rounded-lg bg-sky-100 text-sky-700"><i data-lucide="calendar-plus" class="w-5 h-5"></i></div>
          <h3 class="font-bold text-slate-900 text-base">Agendar Nueva Tarea en Calendario</h3>
        </div>
        <button onclick="window.closeModal('modal-nueva-tarea-calendario')" class="text-slate-400 hover:text-slate-600">
          <i data-lucide="x" class="w-5 h-5"></i>
        </button>
      </div>

      <form id="form-nueva-tarea" class="mt-4 space-y-3.5 text-xs">
        <div>
          <label for="tarea-fecha" class="block font-semibold text-slate-700 mb-1">Fecha Programada *</label>
          <input type="date" id="tarea-fecha" required class="w-full py-2 px-3 border border-slate-300 rounded-lg focus:outline-none focus:border-sky-500">
        </div>
        <div>
          <label for="tarea-hora" class="block font-semibold text-slate-700 mb-1">Hora estimada (24 h) *</label>
          <div class="calendar-task-time">
            <input id="tarea-hora" type="text" inputmode="numeric" autocomplete="off" required
              value="00:00" placeholder="00:00" pattern="([01][0-9]|2[0-3]):[0-5][0-9]"
              title="Escribe cuatro cifras, por ejemplo 1430 para las 14:30."
              class="font-mono py-2 px-3 border border-slate-300 rounded-lg focus:outline-none focus:border-sky-500">
            <button type="button" data-time-step="-60" aria-label="Restar una hora">−1 h</button>
            <button type="button" data-time-step="60" aria-label="Sumar una hora">+1 h</button>
            <button type="button" data-time-step="15" aria-label="Sumar quince minutos">+15 min</button>
          </div>
        </div>

        <div>
          <label class="block font-semibold text-slate-700 mb-1">Título de la Tarea / Servicio *</label>
          <input type="text" id="tarea-titulo" required placeholder="ej. Mantenimiento preventivo semestral switch principal" class="w-full py-2 px-3 border border-slate-300 rounded-lg focus:outline-none focus:border-sky-500">
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block font-semibold text-slate-700 mb-1">Tipo de Actividad *</label>
            <select id="tarea-tipo" required class="w-full py-2 px-3 border border-slate-300 rounded-lg focus:outline-none focus:border-sky-500 bg-white">
              <option value="mantenimiento">Mantenimiento Preventivo</option>
              <option value="reporte">Revisión de un reporte</option>
              <option value="consumible">Reposición de Insumos</option>
              <option value="red">Infraestructura y Redes</option>
              <option value="auditoria">Auditoría / Respaldo DRP</option>
            </select>
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1">Prioridad *</label>
            <select id="tarea-prioridad" required class="w-full py-2 px-3 border border-slate-300 rounded-lg focus:outline-none focus:border-sky-500 bg-white">
              <option value="Media" selected>Media</option>
              <option value="Alta">Alta</option>
              <option value="Critica">Crítica</option>
              <option value="Baja">Baja</option>
            </select>
          </div>
        </div>

        <label id="calendar-report-field" hidden>Reporte del dispositivo
          <select id="calendar-report" class="w-full border rounded-lg p-2"></select>
        </label>
        <div class="calendar-task-assignees grid grid-cols-2 gap-3">
          <div>
            <label class="block font-semibold text-slate-700 mb-1">Técnico Asignado *</label>
            <select id="tarea-tecnico" required class="w-full py-2 px-3 border border-slate-300 rounded-lg focus:outline-none focus:border-sky-500 bg-white">
              <!-- Dinámico -->
            </select>
          </div>
          <div class="calendar-task-asset">
            <label id="tarea-activo-label" for="tarea-activo" class="block font-semibold text-slate-700 mb-1">Activo asociado</label>
            <select id="tarea-activo" class="w-full py-2 px-3 border border-slate-300 rounded-lg focus:outline-none focus:border-sky-500 bg-white">
              <!-- Dinámico -->
            </select>
            <button type="button" id="tarea-activo-elegir" class="calendar-asset-trigger" aria-haspopup="dialog"
              aria-controls="tarea-activo-dialog" aria-labelledby="tarea-activo-label tarea-activo-resumen">
              <span id="tarea-activo-resumen">Buscar o elegir dispositivo</span>
              <i data-lucide="search" aria-hidden="true" class="w-4 h-4"></i>
            </button>
            <dialog id="tarea-activo-dialog" class="calendar-asset-dialog" aria-labelledby="tarea-activo-titulo">
              <header>
                <h4 id="tarea-activo-titulo">Elegir dispositivo</h4>
                <button type="button" autofocus>Cerrar</button>
              </header>
            </dialog>
          </div>
        </div>

        <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
          <button type="button" onclick="window.closeModal('modal-nueva-tarea-calendario')" class="px-4 py-2 rounded-lg border border-slate-300 text-slate-700 font-medium">Cancelar</button>
          <button type="submit" class="px-4 py-2 rounded-lg bg-sky-600 hover:bg-sky-700 text-white font-semibold">Agendar Tarea</button>
        </div>
      </form>
    </div>
  </div>
