  <!-- 8. Modal: Agendar Nueva Tarea en Calendario -->
  <div id="modal-nueva-tarea-calendario" class="fixed inset-0 z-50 modal-backdrop hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200">
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
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label for="tarea-fecha" class="block font-semibold text-slate-700 mb-1">Fecha Programada *</label>
            <input type="date" id="tarea-fecha" required class="w-full py-2 px-3 border border-slate-300 rounded-lg focus:outline-none focus:border-sky-500">
          </div>
          <div>
            <label class="block font-semibold text-slate-700 mb-1">Hora Estimada *</label>
            <input type="time" id="tarea-hora" required value="10:00" class="w-full py-2 px-3 border border-slate-300 rounded-lg focus:outline-none focus:border-sky-500">
          </div>
        </div>

        <div>
          <label class="block font-semibold text-slate-700 mb-1">Título de la Tarea / Servicio *</label>
          <input type="text" id="tarea-titulo" required placeholder="ej. Mantenimiento preventivo semestral switch principal" class="w-full py-2 px-3 border border-slate-300 rounded-lg focus:outline-none focus:border-sky-500">
        </div>

        <div class="grid grid-cols-2 gap-3">
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

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block font-semibold text-slate-700 mb-1">Técnico Asignado *</label>
            <select id="tarea-tecnico" required class="w-full py-2 px-3 border border-slate-300 rounded-lg focus:outline-none focus:border-sky-500 bg-white">
              <!-- Dinámico -->
            </select>
          </div>
          <div>
            <label for="tarea-activo" class="block font-semibold text-slate-700 mb-1">Activo Asociado</label>
            <select id="tarea-activo" class="w-full py-2 px-3 border border-slate-300 rounded-lg focus:outline-none focus:border-sky-500 bg-white">
              <!-- Dinámico -->
            </select>
          </div>
        </div>

        <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
          <button type="button" onclick="window.closeModal('modal-nueva-tarea-calendario')" class="px-4 py-2 rounded-lg border border-slate-300 text-slate-700 font-medium">Cancelar</button>
          <button type="submit" class="px-4 py-2 rounded-lg bg-sky-600 hover:bg-sky-700 text-white font-semibold">Agendar Tarea</button>
        </div>
      </form>
    </div>
  </div>
