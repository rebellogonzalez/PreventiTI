  <!-- Formulario compartido de alta y edición de activos -->
  <div id="modal-nuevo-activo" role="dialog" aria-modal="true" aria-labelledby="asset-form-title" class="fixed inset-0 z-50 modal-backdrop hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-lg w-full max-h-[90vh] overflow-y-auto p-6 shadow-2xl border border-slate-200">
      <div class="flex items-center justify-between pb-3 border-b border-slate-100">
        <h3 id="asset-form-title" class="font-bold text-slate-900 text-base">Incorporar Activo al Inventario</h3>
        <button type="button" aria-label="Cerrar formulario de activo" onclick="window.closeModal('modal-nuevo-activo')" class="text-slate-400 hover:text-slate-600">
          <i data-lucide="x" class="w-5 h-5"></i>
        </button>
      </div>

      <form id="form-nuevo-activo" class="mt-4 space-y-3.5 text-xs">
        <p id="asset-form-error" role="alert" tabindex="-1" hidden class="p-3 rounded-lg border border-rose-200 bg-rose-50 text-rose-700 whitespace-pre-line"></p>
        <p id="asset-form-status" role="status" class="text-slate-500"></p>
        <fieldset id="asset-form-fields" class="space-y-3.5">
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label for="nuevo-activo-cat" class="block font-semibold text-slate-700 mb-1">Familia / Categoría *</label>
            <select id="nuevo-activo-cat" name="categoria_id" required class="w-full py-2 px-3 border border-slate-300 rounded-lg focus:outline-none focus:border-sky-500 bg-white">
              <!-- Dinámico -->
            </select>
          </div>
          <div>
            <label for="nuevo-activo-loc" class="block font-semibold text-slate-700 mb-1">Ubicación Física *</label>
            <select id="nuevo-activo-loc" name="ubicacion_id" required class="w-full py-2 px-3 border border-slate-300 rounded-lg focus:outline-none focus:border-sky-500 bg-white">
              <!-- Dinámico -->
            </select>
          </div>
        </div>

        <div>
          <label for="nuevo-activo-nombre" class="block font-semibold text-slate-700 mb-1">Nombre / Modelo del Equipo *</label>
          <input type="text" id="nuevo-activo-nombre" name="nombre" required maxlength="200" placeholder="ej. Terminal POS TPV Touch 15'' Caja 13" class="w-full py-2 px-3 border border-slate-300 rounded-lg focus:outline-none focus:border-sky-500">
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label for="nuevo-activo-serial" class="block font-semibold text-slate-700 mb-1">Número de Serie Único *</label>
            <input type="text" id="nuevo-activo-serial" name="serie" required maxlength="100" placeholder="SN-POS-8849-XXXX" class="w-full py-2 px-3 border border-slate-300 rounded-lg focus:outline-none focus:border-sky-500 font-mono">
          </div>
          <div>
            <label for="nuevo-activo-resp" class="block font-semibold text-slate-700 mb-1">Responsable Operativo</label>
            <input type="text" id="nuevo-activo-resp" name="responsable" maxlength="150" placeholder="Sin asignar" class="w-full py-2 px-3 border border-slate-300 rounded-lg focus:outline-none focus:border-sky-500">
          </div>
        </div>

        <div>
          <label for="nuevo-activo-frecuencia" class="block font-semibold text-slate-700 mb-1">Frecuencia de mantenimiento (días) *</label>
          <input type="number" id="nuevo-activo-frecuencia" name="frecuencia_service" min="1" max="3650" step="1" value="90" required class="w-full py-2 px-3 border border-slate-300 rounded-lg focus:outline-none focus:border-sky-500">
        </div>
        </fieldset>

        <div class="flex flex-wrap items-center justify-end gap-2 pt-3 border-t border-slate-100">
          <button id="asset-form-reload" type="button" hidden class="px-4 py-2 rounded-lg border border-sky-300 text-sky-700 font-medium">Cargar datos actuales</button>
          <button type="button" onclick="window.closeModal('modal-nuevo-activo')" class="px-4 py-2 rounded-lg border border-slate-300 text-slate-700 font-medium">Cancelar</button>
          <button type="submit" class="px-4 py-2 rounded-lg bg-sky-600 hover:bg-sky-700 text-white font-semibold">Guardar en Inventario</button>
        </div>
      </form>
    </div>
  </div>
