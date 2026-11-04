function normalize(value) {
  return String(value ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('es');
}

function searchableDate(value) {
  const date = String(value ?? '');
  // Keep calendar dates independent of the browser's time zone.
  const parts = date.match(/^(\d{4})-(\d{2})-(\d{2})/);
  return parts ? `${date} ${parts[3]}/${parts[2]}/${parts[1]}` : date;
}

export function selectMaintenanceHistory(maintenances = [], query = '', assets = []) {
  const terms = normalize(query).trim().split(/\s+/).filter(Boolean);
  const assetsById = new Map(assets.map(asset => [String(asset.id), asset]));
  return maintenances.map(maintenance => {
    const asset = maintenance.asset_id != null ? assetsById.get(String(maintenance.asset_id)) : null;
    return {
      ...maintenance,
      asset_name: maintenance.asset_name || asset?.name || 'Dispositivo sin nombre registrado',
      asset_code: maintenance.asset_code || asset?.code || 'Sin código registrado',
      asset_category: maintenance.asset_category || asset?.category_name || '',
      location_name: maintenance.location_name || asset?.location_name || '',
    };
  }).filter(maintenance => {
    const searchable = normalize([
      maintenance.asset_name, maintenance.asset_code, maintenance.asset_category, maintenance.location_name,
      maintenance.technician_name || 'No registrado', maintenance.detail, maintenance.observations,
      searchableDate(maintenance.maintenance_date), searchableDate(maintenance.next_date),
      maintenance.periodicity_days != null ? `${maintenance.periodicity_days} días` : '',
    ].join(' '));
    return terms.every(term => searchable.includes(term));
  });
}
