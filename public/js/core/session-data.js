// Estado de esta sesión: los datos y permisos siempre provienen del servidor.
class PreventiTIDatabase {
  static load(profile = window.currentUserProfile) {
    return {
      users: profile ? [profile.user] : [], currentUserId: profile?.user.id,
      currentUserRole: profile?.role, allowedAreas: profile?.allowed_areas || [],
      assets: [], archivedAssets: [], reportAssets: [], categories: [], locations: [], reports: [],
      notifications: [], maintenances: [], calendarTasks: [], taskAssignees: [],
      auditLogs: [], componentsReplaced: [], recurringIncidents: [], budgetMetrics: {},
    };
  }
  static save() { /* El servidor persiste las operaciones autorizadas. */ }
}
window.PreventiTIDB = PreventiTIDatabase;
window.preventitiDataReady = false;
// Descartar el antiguo perfil de demostración; nunca se utiliza como identidad.
try { localStorage.removeItem('PREVENTITI_DATA_V4'); } catch (_) {}
