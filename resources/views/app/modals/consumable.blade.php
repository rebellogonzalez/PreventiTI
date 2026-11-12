  <!-- 5. Modal: Actualizar / Reponer Consumible -->
  <div id="modal-actualizar-consumible" class="fixed inset-0 z-50 modal-backdrop hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200">
      <div class="flex items-center justify-between pb-3 border-b border-slate-100">
        <h3 id="consumible-modal-title" class="font-bold text-slate-900 text-sm">Actualizar Consumible</h3>
        <button onclick="window.closeModal('modal-actualizar-consumible')" class="text-slate-400 hover:text-slate-600">
          <i data-lucide="x" class="w-5 h-5"></i>
        </button>
      </div>

      <form id="form-actualizar-consumible" class="mt-4 space-y-4 text-xs">
        <label class="block">Tarea de calendario<select id="consumible-task" class="w-full border rounded-lg p-2"></select></label>
        <input type="hidden" id="consumible-asset-id">

        <div>
          <div class="flex justify-between items-center mb-1.5">
            <label class="font-semibold text-slate-700">Nivel de Carga Remanente:</label>
            <span id="consumible-level-display" class="font-bold text-base text-sky-600 font-mono">100%</span>
          </div>
          <input type="range" id="consumible-level-slider" min="0" max="100" step="5" value="100" class="w-full accent-sky-600">
          <div class="flex justify-between text-[10px] text-slate-400 mt-1">
            <span>0% Agotado</span>
            <span class="text-amber-600 font-semibold">20% Umbral Atención</span>
            <span>100% Lleno</span>
          </div>
        </div>

        <div class="flex gap-2">
          <button type="button" onclick="document.getElementById('consumible-level-slider').value=100; document.getElementById('consumible-level-display').textContent='100%';" class="flex-1 py-1.5 px-2 rounded bg-emerald-50 text-emerald-800 border border-emerald-200 font-medium text-center">
            Reposición 100%
          </button>
          <button type="button" onclick="document.getElementById('consumible-level-slider').value=15; document.getElementById('consumible-level-display').textContent='15%';" class="flex-1 py-1.5 px-2 rounded bg-amber-50 text-amber-800 border border-amber-200 font-medium text-center">
            Forzar Alerta 15%
          </button>
        </div>

        <div class="flex items-center justify-end gap-2 pt-2">
          <button type="button" onclick="window.closeModal('modal-actualizar-consumible')" class="px-4 py-2 rounded-lg border border-slate-300 text-slate-700 font-medium">Cancelar</button>
          <button type="submit" class="px-4 py-2 rounded-lg bg-sky-600 hover:bg-sky-700 text-white font-semibold">Guardar Nivel</button>
        </div>
      </form>
    </div>
  </div>
