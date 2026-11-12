  <!-- 1. Modal: crear reporte -->
  <div id="modal-nuevo-reporte" class="fixed inset-0 z-50 modal-backdrop hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200">
      <div class="flex items-center justify-between pb-3 border-b border-slate-100">
        <div class="flex items-center gap-2">
          <div class="p-2 rounded-lg bg-sky-100 text-sky-700"><i data-lucide="file-plus-2" class="w-5 h-5"></i></div>
          <h3 class="font-bold text-slate-900 text-base">Nuevo reporte de situación</h3>
        </div>
        <button onclick="window.closeModal('modal-nuevo-reporte')" class="text-slate-400 hover:text-slate-600">
          <i data-lucide="x" class="w-5 h-5"></i>
        </button>
      </div>

      <form id="form-nuevo-reporte" class="mt-4 space-y-4 text-xs">
        <div>
          <label for="reporte-activo-select" class="block font-semibold text-slate-700 mb-1">Activo Afectado *</label>
          <select id="reporte-activo-select" required class="w-full py-2 px-3 border border-slate-300 rounded-lg focus:outline-none focus:border-sky-500 bg-white">
            <!-- Opciones cargadas dinámicamente -->
          </select>
        </div>

        <div>
          <label class="block font-semibold text-slate-700 mb-1">Nivel de Urgencia / Prioridad *</label>
          <select id="reporte-prioridad-select" required class="w-full py-2 px-3 border border-slate-300 rounded-lg focus:outline-none focus:border-sky-500 bg-white">
            <option value="Media" selected>Media (Operación normal afectada parcialmente)</option>
            <option value="Baja">Baja (Mantenimiento menor o estético)</option>
            <option value="Alta">Alta (Impacto directo en caja o puesto de venta)</option>
            <option value="Critica">Crítica (Equipo bloqueante o parada de línea)</option>
          </select>
        </div>

        <div>
          <label class="block font-semibold text-slate-700 mb-1">Descripción Detallada de la Falla *</label>
          <textarea id="reporte-descripcion-input" rows="3" minlength="5" maxlength="2000" required placeholder="Explique los síntomas observados, mensajes de error o comportamiento anormal..." class="w-full p-2.5 border border-slate-300 rounded-lg focus:outline-none focus:border-sky-500"></textarea>
        </div>

        <div class="p-3 bg-slate-50 rounded-lg text-slate-500 text-[11px]">
          Al guardar, el reporte quedará registrado y se creará una alerta para jefatura técnica.
        </div>

        <div class="flex items-center justify-end gap-2 pt-2">
          <button type="button" onclick="window.closeModal('modal-nuevo-reporte')" class="px-4 py-2 rounded-lg border border-slate-300 text-slate-700 font-medium hover:bg-slate-50">Cancelar</button>
          <button type="submit" class="px-4 py-2 rounded-lg bg-sky-600 hover:bg-sky-700 text-white font-semibold shadow-sm">Guardar reporte</button>
        </div>
      </form>
    </div>
  </div>
