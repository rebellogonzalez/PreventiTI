  <!-- 7. Modal: Ficha Técnica Detallada de Activo -->
  <div id="modal-detalle-activo" role="dialog" aria-modal="true" aria-label="Ficha técnica del dispositivo" class="fixed inset-0 z-50 modal-backdrop hidden items-center justify-center p-4">
    <div class="asset-detail-panel bg-white rounded-2xl max-w-2xl w-full p-4 sm:p-6 shadow-2xl border border-slate-200">
      <div id="detalle-activo-content" class="min-h-0 overflow-y-auto">
        <!-- Rellenado dinámicamente -->
      </div>
      <div class="mt-3 pt-3 shrink-0 border-t border-slate-100 text-right">
        <button onclick="window.closeModal('modal-detalle-activo')" class="px-4 py-2 rounded-lg bg-slate-900 text-white text-xs font-semibold">Cerrar Ficha</button>
      </div>
    </div>
  </div>
