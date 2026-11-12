  <!-- 4. Modal: Registrar Mantenimiento Preventivo (Calculadora Determinista) -->
  <div id="modal-registrar-mantenimiento" class="fixed inset-0 z-50 modal-backdrop hidden items-center justify-center p-4">
    <div class="bg-white max-h-[90dvh] overflow-y-auto rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200">
      <div class="flex items-center justify-between pb-3 border-b border-slate-100">
        <div class="flex items-center gap-2">
          <div class="p-2 rounded-lg bg-slate-100 text-slate-800"><i data-lucide="wrench" class="w-5 h-5"></i></div>
          <h3 class="font-bold text-slate-900 text-base">Registrar Inspección de Mantenimiento Preventivo</h3>
        </div>
        <button onclick="window.closeModal('modal-registrar-mantenimiento')" class="text-slate-400 hover:text-slate-600">
          <i data-lucide="x" class="w-5 h-5"></i>
        </button>
      </div>

      <form id="form-registrar-mantenimiento" class="mt-4 space-y-4 text-xs">
        <p id="mant-scheduled-context" class="font-semibold text-sky-800" hidden></p>
        <div>
          <label for="mant-asset-select" class="block font-semibold text-slate-700 mb-1">Activo Intervenido *</label>
          <select id="mant-asset-select" required class="w-full py-2 px-3 border border-slate-300 rounded-lg focus:outline-none focus:border-sky-500 bg-white">
            <!-- Dinámico -->
          </select>
        </div>

        <div>
          <label class="block font-semibold text-slate-700 mb-1">Fecha de Ejecución de la Inspección *</label>
          <input type="date" id="mant-date-input" required class="w-full py-2 px-3 border border-slate-300 rounded-lg focus:outline-none focus:border-sky-500">
        </div>

        <label class="block font-semibold">Tarea de calendario
          <select id="mant-task" class="w-full border rounded-lg p-2"></select>
        </label>
        <button id="mant-schedule" type="button" data-permission="can_manage_calendar" class="text-sky-700 underline">Programar una visita</button>

        <!-- Caja de Vista Previa del Cálculo Determinista -->
        <div id="mant-calc-preview" class="p-3 bg-slate-900 text-slate-200 rounded-lg text-xs font-mono">
          Seleccione un activo y fecha para proyectar la próxima revisión.
        </div>

        <div>
          <label class="block font-semibold text-slate-700 mb-1">Detalle Técnico de Tareas Ejecutadas *</label>
          <textarea id="mant-detail-input" rows="3" required placeholder="Limpieza, verificación de voltajes, lubricación, actualización de firmware, reemplazo de filtros..." class="w-full p-2.5 border border-slate-300 rounded-lg focus:outline-none focus:border-sky-500"></textarea>
        </div>

        <label class="block font-semibold">Resultado
          <select id="mant-outcome" class="w-full border rounded-lg p-2">
            <option value="ok">Sin hallazgos</option><option value="new">Generar reporte de falla</option><option value="existing">Vincular reporte existente</option>
          </select>
        </label>
        <label id="mant-link-field" hidden>Reporte del dispositivo<select id="mant-report" class="w-full border rounded-lg p-2"></select></label>
        <div id="mant-finding-fields" hidden>
          <label>Falla detectada<textarea id="mant-finding" minlength="5" maxlength="2000" class="w-full border rounded-lg p-2"></textarea></label>
          <label>Prioridad<select id="mant-priority" class="w-full border rounded-lg p-2"><option>Baja</option><option selected>Media</option><option>Alta</option><option value="Critica">Crítica</option></select></label>
        </div>
        <div class="flex items-center justify-end gap-2 pt-2">
          <button type="button" onclick="window.closeModal('modal-registrar-mantenimiento')" class="px-4 py-2 rounded-lg border border-slate-300 text-slate-700 font-medium">Cancelar</button>
          <button type="submit" class="px-4 py-2 rounded-lg bg-slate-900 hover:bg-slate-800 text-white font-semibold">Guardar y Proyectar Próxima Fecha</button>
        </div>
      </form>
    </div>
  </div>
