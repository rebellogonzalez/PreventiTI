/** Status and search rules shared by the inventory filters and badges. */
(function (root) {
  'use strict';

  const statuses = Object.freeze({
    normal: 'Operativo',
    atencion: 'En atención',
    critica: 'Estado crítico',
    mantenimiento: 'En mantenimiento',
  });

  function normalize(value) {
    return String(value ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('es').trim();
  }

  function statusFor(asset) {
    const state = normalize(asset.state);
    if (asset.active === false || asset.active === 0 || ['retirado', 'fuera de servicio'].includes(state)) return 'critica';
    // An intervention in progress has its own status, even if service is overdue.
    if (state === 'en mantenimiento') return 'mantenimiento';
    if (state === 'estado critico') return 'critica';
    if (state === 'en atencion') return 'atencion';
    if (['normal', 'atencion', 'critica'].includes(asset.status_alert)) return asset.status_alert;

    // Standalone records may not include the server's calculated alert.
    const days = asset.days_to_maintenance;
    if (days != null && days !== '' && Number(days) < 0) return 'critica';
    if (days != null && days !== '' && Number(days) <= 7) return 'atencion';
    if (asset.has_consumable && asset.consumable_level != null && asset.consumable_level !== '' && Number(asset.consumable_level) <= 20) return 'atencion';
    return 'normal';
  }

  function isRetired(asset) {
    return normalize(asset.state) === 'retirado';
  }

  function selectAssets(assets, filters = {}) {
    const terms = normalize(filters.query).split(/\s+/).filter(Boolean);
    return assets.filter(asset => {
      const status = statusFor(asset);
      if (filters.status && status !== filters.status) return false;
      if (filters.category && String(asset.category_id) !== String(filters.category)) return false;
      if (filters.location && String(asset.location_id) !== String(filters.location)) return false;
      const searchable = normalize([
        asset.code, asset.serial, asset.name, asset.responsible,
        asset.category_name, asset.location_name, statuses[status],
      ].join(' '));
      return terms.every(term => searchable.includes(term));
    });
  }

  function escapeHTML(value) {
    return String(value ?? '').replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char]);
  }

  root.PreventiTIInventory = Object.freeze({ statuses, statusFor, selectAssets, escapeHTML, isRetired });
})(typeof window === 'undefined' ? globalThis : window);
