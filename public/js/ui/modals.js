export function initializeModals({ db, saveDB }) {
  window.openModal = function (modalId) {
    const permissions = window.currentUserProfile.permissions;
    const required = { 'modal-nuevo-activo': 'can_manage_assets', 'modal-registrar-mantenimiento': 'can_maintain_assets', 'modal-actualizar-consumible': 'can_maintain_assets', 'modal-nueva-tarea-calendario': 'can_manage_calendar' }[modalId];
    if (required && !permissions[required]) return;

    const modal = document.getElementById(modalId);
    if (modal && modal.classList.contains('hidden')) {
      modal.classList.remove('hidden');
      modal.classList.add('flex');
      modal.firstElementChild.scrollTop = 0;
    }
  };

  window.closeModal = function (modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
      modal.classList.add('hidden');
      modal.classList.remove('flex');
      modal.dispatchEvent(new Event('close'));
    }
  };

  window.viewAssetDetail = function (assetId) {
    window.openModal('modal-detalle-activo');
    return window.renderAssetDetail(assetId);
  };

  window.viewInspection = async function (assetId, inspectionId) {
    document.querySelector('.report-dialog[open]')?.close();
    await window.viewAssetDetail(assetId);
    document.querySelector(`[data-inspection="${Number(inspectionId)}"]`)?.focus();
  };

  const changingAssets = new Set();
  window.toggleAssetActive = async function (assetId, mapAsset = null) {
    if (!window.currentUserProfile.permissions.can_manage_assets) return;
    const asset = mapAsset || db.assets.find(a => a.id === assetId) || db.archivedAssets.find(a => a.id === assetId);
    if (!asset || changingAssets.has(assetId)) return;
    const retired = window.PreventiTIInventory.isRetired(asset);
    const visits = (db.calendarTasks || []).filter(task => task.asset_id === assetId && !task.completed && !task.cancel_reason).length;
    const question = retired
      ? `¿Reincorporar ${asset.code} al inventario y al mapa? Recuperará su estado anterior. Si se desconoce, quedará fuera de servicio.`
      : `¿Dar de baja a ${asset.code} (${asset.name})? Saldrá del mapa y del inventario.${visits ? ` Se cancelarán ${visits} visitas pendientes.` : ''} Su ficha, reportes y mantenimientos quedarán en el histórico.`;
    if (!confirm(question)) return;
    changingAssets.add(assetId);
    try {
      const response = await window.PreventiTIAPI.toggleAssetActive(assetId, asset.state);
      window.showToast(retired ? 'Dispositivo reincorporado' : 'Dispositivo en el histórico', response.message, 'success');
      try {
        await window.PreventiTIAPI.syncWithBackend();
      } catch {
        window.showToast('Cambio guardado', 'Recarga la página para consultar el inventario actualizado.', 'warning');
      }
    } catch (error) {
      window.showToast('No se pudo cambiar el estado', error.message, 'danger');
    } finally {
      changingAssets.delete(assetId);
    }
  };

  window.openCreateReportForAsset = function (assetId) {
    const assetSelect = document.getElementById('reporte-activo-select');
    if (assetSelect) {
      assetSelect.value = assetId;
      assetSelect.dispatchEvent(new Event('change', { bubbles: true }));
    }
    window.openModal('modal-nuevo-reporte');
  };
  window.openModalConsumable = function (assetId, taskId = null) {
    const asset = db.assets.find(a => a.id === assetId);
    if (!asset || !asset.has_consumable) return;

    const modal = document.getElementById('modal-actualizar-consumible');
    const title = document.getElementById('consumible-modal-title');
    const assetIdInput = document.getElementById('consumible-asset-id');
    const levelInput = document.getElementById('consumible-level-slider');
    const levelDisplay = document.getElementById('consumible-level-display');
    if (!modal || !title || !assetIdInput || !levelInput) return;

    title.textContent = `Reponer ${asset.consumable_type} — ${asset.code}`;
    const choice = document.getElementById('consumible-task');
    const visits = (db.calendarTasks || []).filter(t => t.asset_id === asset.id && t.technician_id && t.type === 'consumible' && !t.completed && !t.cancel_reason);
    if (choice) {
      choice.replaceChildren(new Option('Reposición sin tarea programada', ''),
        ...visits.map(t => new Option(`${t.date} · ${t.time} · ${t.title}`, t.id)));
      choice.value = taskId || '';
    }
    assetIdInput.value = asset.id;
    levelInput.value = asset.consumable_level ?? 50;
    if (levelDisplay) levelDisplay.textContent = `${levelInput.value}%`;

    window.openModal('modal-actualizar-consumible');
  };

  window.renderAssetDetail = async function(assetId) {
    const escapeDetail = value => String(value ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
    const container = document.getElementById('detalle-activo-content');
    if (!container) return;

    // 1. Estado de carga
    container.innerHTML = `
      <div class="flex flex-col items-center justify-center py-12">
        <svg class="animate-spin -ml-1 mr-3 h-8 w-8 text-sky-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <span class="mt-2 text-sm text-slate-500 font-medium">Obteniendo datos de prevención...</span>
      </div>
    `;

    try {
      // 2. Fetch desde el backend
      const res = await window.PreventiTIAPI.request('/activos/' + assetId);
      const asset = JSON.parse(JSON.stringify(res.data.asset), (key, value) => typeof value === 'string' ? escapeDetail(value) : value);
      const statusAlert = res.data.status_alert;
      
      const retired = window.PreventiTIInventory.isRetired({ state: asset.estado });
      const reports = asset.reportes || [];
      const maintenances = asset.mantenimientos || [];

      // Colores de estado
      const badgeClass = retired ? 'bg-slate-100 text-slate-700' : statusAlert === 'critica' ? 'bg-rose-100 text-rose-700 border border-rose-200' :
                         statusAlert === 'atencion' ? 'bg-amber-100 text-amber-700 border border-amber-200' :
                         'bg-emerald-100 text-emerald-700 border border-emerald-200';
      const iconAlert = retired ? 'archive' : statusAlert === 'critica' ? 'alert-octagon' :
                        statusAlert === 'atencion' ? 'alert-triangle' : 'check-circle';

      // 3. Renderizar la ficha con los datos reales
      container.innerHTML = `
        <div class="flex flex-wrap items-center justify-between gap-2 pb-3 border-b border-slate-200 mb-4">
          <h3 class="font-bold text-slate-900 text-lg flex items-center gap-2">
            <i data-lucide="cpu" class="w-5 h-5 text-sky-600"></i> Ficha Técnica del Dispositivo
          </h3>
          <span class="px-3 py-1 rounded-full text-[10px] font-extrabold ${badgeClass} flex items-center gap-1.5 uppercase tracking-wide shadow-sm">
            <i data-lucide="${iconAlert}" class="w-3.5 h-3.5"></i> ${asset.estado}
          </span>
        </div>

        ${window.currentUserProfile?.permissions?.can_edit_assets ? `
          <button onclick="window.openEditAsset(${Number(assetId)})" class="mb-4 px-4 py-2 rounded-lg bg-sky-600 text-white text-xs font-semibold">Editar activo</button>
        ` : ''}

        <div class="space-y-4">
          <!-- Identificación y Ubicación -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div class="p-4 bg-white rounded-xl border border-slate-200 shadow-sm hover:shadow-md transition-shadow">
              <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5 flex items-center gap-1"><i data-lucide="tag" class="w-3 h-3"></i> Identificación</div>
              <div class="font-extrabold text-sm text-slate-900">${asset.nombre}</div>
              <dl class="asset-detail-fields">
                <dt>S/N</dt><dd class="font-mono">${asset.serie || 'Sin registrar'}</dd>
                <dt>Categoría</dt><dd>${asset.categoria ? asset.categoria.nombre : 'N/A'}</dd>
              </dl>
            </div>
            <div class="p-4 bg-white rounded-xl border border-slate-200 shadow-sm hover:shadow-md transition-shadow">
              <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5 flex items-center gap-1"><i data-lucide="map-pin" class="w-3 h-3"></i> Ubicación y Operación</div>
              <div class="text-sm font-extrabold text-slate-900">${asset.ubicacion_detalle ? asset.ubicacion_detalle.nombre : (asset.ubicacion || 'N/A')}</div>
              <dl class="asset-detail-fields">
                <dt>Coordenadas</dt><dd class="font-mono">${asset.map_position ? `X: ${(asset.map_position.x * 100).toFixed(1)}% · Z: ${(asset.map_position.z * 100).toFixed(1)}%` : 'Sin confirmar'}</dd>
                <dt>Responsable</dt><dd class="text-sky-700">${asset.responsable}</dd>
              </dl>
            </div>
          </div>

          <!-- Mantenimiento -->
          <div class="border border-slate-200 rounded-xl overflow-hidden shadow-sm">
            <div class="bg-slate-50 px-4 py-3 border-b border-slate-200 flex items-center gap-2 text-sm font-bold text-slate-800">
              <i data-lucide="wrench" class="w-4 h-4 text-slate-500"></i> Plan de Mantenimiento Preventivo
            </div>
            <div class="p-4 bg-white grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <div class="text-[10px] uppercase tracking-widest font-bold text-slate-400">Última Revisión</div>
                <div class="font-bold text-slate-900 text-sm mt-1 flex items-center gap-1.5"><i data-lucide="calendar-check" class="w-3.5 h-3.5 text-emerald-500"></i> ${asset.last_maintenance || 'Sin registro'}</div>
              </div>
              <div>
                <div class="text-[10px] uppercase tracking-widest font-bold text-slate-400">${retired ? 'Fecha de baja' : 'Próximo Service'}</div>
                <div class="font-bold text-slate-900 text-sm mt-1 flex flex-wrap items-center gap-2">
                  <span class="flex items-center gap-1.5"><i data-lucide="calendar-clock" class="w-3.5 h-3.5 text-sky-500"></i> ${retired ? (asset.fecha_baja ? new Date(asset.fecha_baja).toLocaleDateString('es-UY') : 'Sin fecha registrada') : (asset.next_maintenance || 'Sin fecha')}</span>
                  <span ${retired ? 'hidden' : ''} class="px-2 py-0.5 bg-sky-50 text-sky-700 rounded text-[10px] font-bold">${res.data.days_to_maintenance == null ? 'Sin programación' : `En ${res.data.days_to_maintenance} días`}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Reportes -->
          <div class="border ${reports.length > 0 ? 'border-amber-200' : 'border-slate-200'} rounded-xl overflow-hidden shadow-sm">
            <div class="${reports.length > 0 ? 'bg-amber-50 border-amber-200 text-amber-900' : 'bg-slate-50 border-slate-200 text-slate-800'} px-4 py-3 border-b flex items-center gap-2 text-sm font-bold">
              <i data-lucide="file-text" class="w-4 h-4"></i> Reportes registrados
            </div>
            <div class="bg-white divide-y divide-slate-100">
              ${reports.length === 0 ? '<div class="p-6 text-sm font-medium text-slate-400 text-center">Sin reportes visibles para este dispositivo.</div>' : reports.map(item => `
                <div class="p-4 hover:bg-slate-50 transition-colors">
                  <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                    <span class="text-xs font-bold text-slate-700">${item.codigo} · ${item.estado_reporte}</span>
                    <span class="text-[10px] font-semibold text-slate-400">${new Date(item.fecha_creacion || item.created_at).toLocaleDateString('es-UY', { timeZone: 'America/Montevideo' })}</span>
                  </div>
                  <p class="text-xs text-slate-600 leading-relaxed">${item.descripcion}</p>
                  <button type="button" class="report-button report-button--secondary mt-2" onclick="window.viewReportDetail(${Number(item.id)})">Ver reporte completo</button>
                  ${item.diagnostico_final ? `<p class="text-xs text-slate-600 mt-2"><strong>Diagnóstico</strong> · ${item.diagnostico_final}</p>` : ''}
                  ${item.informe_accionar || item.resolucion ? `<p class="text-xs text-slate-600 mt-2"><strong>Intervención</strong> · ${item.informe_accionar || item.resolucion}</p>` : ''}
                </div>
              `).join('')}
            </div>
          </div>

          <!-- Historial de Reparaciones / Intervenciones -->
          <div class="border border-sky-200 rounded-lg overflow-hidden">
            <div class="bg-sky-50 p-2.5 border-b border-sky-200 flex items-center gap-2 text-sm font-bold text-sky-900">
              <i data-lucide="history" class="w-4 h-4 text-sky-600"></i> Inspecciones e intervenciones
            </div>
            <div class="bg-white divide-y divide-slate-100">
              ${maintenances.length === 0 ? '<div class="p-3 text-xs text-slate-400 text-center">Sin inspecciones ni intervenciones registradas.</div>' :
                maintenances.map(m => `
                  <div class="p-3" tabindex="-1" data-inspection="${Number(m.id)}">
                    <div class="flex items-center justify-between mb-1">
                      <span class="text-xs font-bold text-slate-800">${m.fecha_mantenimiento}${m.requires_review ? ' · Fecha por verificar' : ''}</span>
                    </div>
                    <p class="text-xs text-slate-600">${m.descripcion || m.detalle || 'Sin descripción'}</p>
                    ${m.reporte_id ? `<button class="text-sky-700 underline" onclick="window.viewReportDetail(${Number(m.reporte_id)})">Ver reporte #${Number(m.reporte_id)}</button>` : ''}
                    ${m.observaciones ? `<p class="text-xs text-slate-500 mt-1">${m.observaciones}</p>` : ''}
                  </div>
                `).join('')
              }
            </div>
          </div>
        </div>
      `;

      if (window.lucide) window.lucide.createIcons();

    } catch (err) {
      container.innerHTML = `
        <div class="p-4 bg-rose-50 border border-rose-200 rounded-lg text-rose-700 text-sm">
          <i data-lucide="alert-triangle" class="w-5 h-5 mb-2"></i>
          <p class="font-bold">Error al cargar información</p>
          <p class="text-xs mt-1">${escapeDetail(err.message)}</p>
        </div>
      `;
      if (window.lucide) window.lucide.createIcons();
    }
  };
}
