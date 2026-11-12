// PreventiTI — Adaptador de Cliente API para Laravel Backend
// Conecta la SPA con las rutas REST /api/* y gestiona la sincronización con RBAC

(function () {
  const API_BASE = '/api';
  let staffRevision;
  let reportRevision;
  let operationRevision;
  let reportWrites = 0;
  let checkingChanges = false;
  const editing = () => document.querySelector('dialog[open], .modal-backdrop:not(.hidden), #view-container form:focus-within');
  let pendingSync = Promise.resolve();

  class ApiClient {
    static getHeaders() {
      const headers = {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      };
      const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
      if (csrf) headers['X-CSRF-TOKEN'] = csrf;
      return headers;
    }

    static async request(endpoint, options = {}) {
      try {
        const response = await fetch(`${API_BASE}${endpoint}`, {
          credentials: 'same-origin',
          ...options,
          headers: {
            ...this.getHeaders(),
            ...(options.headers || {}),
          },
        });

        if ([401, 419].includes(response.status) && endpoint !== '/auth/login') {
          console.warn('[API Client] Sesión no autenticada. Redirigiendo a /login...');
          if (!window.location.pathname.includes('/login')) {
            window.location.href = '/login';
          }
          throw new Error('No autenticado');
        }

        const data = await response.json();
        if (!response.ok) {
          const error = new Error(data.error || Object.values(data.errors || {}).flat()[0] || data.message || `Error HTTP ${response.status}`);
          error.status = response.status;
          error.errors = data.errors || {};
          throw error;
        }
        return data;
      } catch (err) {
        if (err.name !== 'AbortError') console.warn(`[API Client Warning] Petición a ${endpoint}:`, err.message);
        throw err;
      }
    }

    // 1. Carga inicial sincronizada con RBAC según permisos reales
    static syncWithBackend(background = false) {
      const result = pendingSync.then(() => this.loadFromBackend(background));
      pendingSync = result.catch(() => {});
      return result;
    }

    static async loadFromBackend(background) {
      const writeVersion = reportWrites;
      try {
        const profile = await this.request('/auth/me');
        const state = window.PreventiTIDB.load(profile);
        state.currentUserRole = profile.role;
        state.allowedAreas = profile.allowed_areas;

        const can = area => profile.allowed_areas.includes(area);
        const jobs = [
          this.request('/reportes?resumen=1').then(r => { state.reports = r.data; }),
          this.request('/reportes/activos-disponibles').then(r => { state.reportAssets = r.data; }),
        ];
        if (can('activos')) {
          jobs.push(this.request('/activos?incluir_historico=1').then(r => {
            state.assets = r.data;
            state.archivedAssets = r.historico;
          }));
          jobs.push(this.request('/activos/categories').then(r => { state.categories = r.data.map(c => ({ ...c, name: c.nombre, count: c.activos_count })); }));
          jobs.push(this.request('/activos/locations').then(r => { state.locations = r.data; }));
        }
        if (can('mantenimientos')) jobs.push(this.request('/mantenimientos').then(r => { state.maintenances = r.data; }));
        if (can('calendario')) jobs.push(this.request('/calendario').then(r => { state.calendarTasks = r.data.tasks; }));
        if (profile.permissions.can_view_notifications) jobs.push(this.request('/notificaciones').then(r => { state.notifications = r.data; }));
        if (profile.permissions.can_view_audit) jobs.push(this.request('/dashboard/audit-logs').then(r => {
          state.auditLogs = r.data.map(item => ({ id: item.id, timestamp: item.fecha_auditoria, user_name: 'Registro del sistema', action: 'Auditoría', detail: item.descripcion }));
        }));
        if (profile.permissions.can_manage_calendar) jobs.push(this.request('/auth/task-assignees').then(r => { state.taskAssignees = r.data; }));
        if (can('usuarios')) jobs.push(this.request('/auth/users').then(r => { state.users = r.data; }));
        if (can('estadisticas')) jobs.push(this.request('/estadisticas').then(r => {
          state.serverStats = r.data;
          state.budgetMetrics = r.data.budget || {};
          state.componentsReplaced = r.data.components_replaced || [];
          state.recurringIncidents = r.data.recurring_incidents || [];
        }));
        await Promise.all(jobs);
        if (background && (editing() || writeVersion !== reportWrites)) return;
        window.currentUserProfile = profile;
        this.applySidebarPermissions(profile.allowed_areas);
        Object.keys(window.db).forEach(key => delete window.db[key]);
        Object.assign(window.db, state);
        staffRevision = profile.staff_revision;
        reportRevision = profile.report_revision;
        operationRevision = profile.operation_revision;
        window.preventitiDataReady = true;
        window.refreshSessionUI?.();
      } catch (error) {
        if (background) throw error;
        window.preventitiDataReady = false;
        const content = document.getElementById('view-container');
        if (content) content.textContent = 'No se pudieron cargar los datos autorizados. Recarga la página para reintentar.';
        console.warn('Carga de sesión:', error.message);
        throw error;
      }
    }

    // Ocultar del Sidebar las opciones a las que el usuario no tiene acceso
    static applySidebarPermissions(allowedAreas) {
      document.querySelectorAll('.nav-link').forEach(link => {
        const route = link.getAttribute('data-route');
        const area = { inventario: 'activos', analisis: 'estadisticas' }[route] || route;
        if (route && !allowedAreas.includes(area) && !allowedAreas.includes(route)) {
          link.style.display = 'none';
        } else {
          link.style.display = 'flex';
        }
      });
      // Group headings follow the existing visibility of their links.
      document.querySelectorAll('[data-nav-group]').forEach(group => {
        group.hidden = !Array.from(group.querySelectorAll('.nav-link')).some(link => link.style.display !== 'none');
      });
    }

    // 2. Operaciones de Autenticación
    static async login(email, password, remember = false) {
      return await this.request('/auth/login', {
        method: 'POST',
        body: JSON.stringify({ email, password, remember }),
      });
    }

    static async logout() {
      try {
        await this.request('/auth/logout', { method: 'POST' });
      } finally {
        window.location.href = '/login';
      }
    }

    // 3. Operaciones de Activos
    static async createAsset(data) {
      return await this.request('/activos', {
        method: 'POST',
        body: JSON.stringify(data),
      });
    }

    static async toggleAssetActive(assetId, expectedState) {
      return await this.request(`/activos/${assetId}/toggle`, {
        method: 'PATCH',
        body: JSON.stringify({ expected_state: expectedState }),
      });
    }

    static async updateAsset(assetId, data) {
      return await this.request(`/activos/${assetId}`, {
        method: 'PATCH',
        body: JSON.stringify(data),
      });
    }

    // 4. Reportes y notificaciones
    static async createReport(assetId, priority, description, submissionId) {
      return await this.request('/reportes', {
        method: 'POST',
        body: JSON.stringify({
          asset_id: assetId,
          priority: priority,
          description: description,
          submission_id: submissionId,
        }),
      });
    }

    static reportSaved() {
      reportWrites++;
      this.checkChanges?.();
    }

    static reportHistory(filters, page, signal) {
      const params = new URLSearchParams(Object.entries(filters).filter(([, value]) => value));
      params.set('page', page);
      return this.request('/reportes/historial?' + params, { signal });
    }

    static async reportTechnicians() {
      return await this.request('/reportes/tecnicos');
    }

    static async assignReport(reportId, technicianId, reassignmentReason = null, expectedVersion) {
      return await this.request(`/reportes/${reportId}/asignacion`, {
        method: 'PATCH',
        body: JSON.stringify({
          technician_id: technicianId,
          reassignment_reason: reassignmentReason,
          expected_version: expectedVersion,
        }),
      });
    }

    static async transitionReport(reportId, status, actionReport = null, finalDiagnosis = null, expectedVersion) {
      return await this.request(`/reportes/${reportId}/estado`, {
        method: 'PATCH',
        body: JSON.stringify({
          status,
          action_report: actionReport,
          final_diagnosis: finalDiagnosis,
          expected_version: expectedVersion,
        }),
      });
    }

    static async markNotificationRead(notificationId) {
      return await this.request(`/notificaciones/${notificationId}/leer`, {
        method: 'PATCH',
      });
    }

    // 5. Mantenimientos
    static async registerMaintenance(assetId, date, detail, options = {}) {
      return await this.request('/mantenimientos', {
        method: 'POST',
        body: JSON.stringify({
          asset_id: assetId,
          maintenance_date: date,
          detail, ...options,
        }),
      });
    }

    // 6. Consumibles
    static async updateConsumableLevel(assetId, level, taskId = null) {
      return await this.request(`/consumibles/${assetId}`, {
        method: 'PATCH',
        body: JSON.stringify({ level, task_id: taskId }),
      });
    }

    // 7. Calendario
    static async createCalendarTask(data) {
      return await this.request('/calendario', {
        method: 'POST',
        body: JSON.stringify(data),
      });
    }

    static async toggleCalendarTask(taskId, completed, version) {
      return await this.request(`/calendario/${taskId}/toggle`, {
        method: 'PATCH', body: JSON.stringify({ completed, expected_version: version }),
      });
    }

    // 8. Infraestructura
    static async scanNetwork() {
      return await this.request('/infraestructura/scan', {
        method: 'POST',
      });
    }

    static async runDrpBackup() {
      return await this.request('/infraestructura/drp-backup', {
        method: 'POST',
      });
    }
  }

  window.PreventiTIAPI = ApiClient;

  document.addEventListener('DOMContentLoaded', () => {
    ApiClient.syncWithBackend().catch(() => {});
    async function checkChanges() {
      if (document.hidden || checkingChanges || staffRevision === undefined) return;
      checkingChanges = true;
      try {
        const current = await ApiClient.request('/auth/staff-revision');
        if ((current.staff_revision !== staffRevision || current.report_revision !== reportRevision || current.operation_revision !== operationRevision) && !editing()) {
          await ApiClient.syncWithBackend(true);
        }
      } catch (error) {
        if (error.status === 403) window.location.href = '/login';
      } finally {
        checkingChanges = false;
      }
    }
    ApiClient.checkChanges = checkChanges;
    document.addEventListener('close', checkChanges, true);
    window.addEventListener('focus', checkChanges);
    window.addEventListener('hashchange', checkChanges);
    document.addEventListener('visibilitychange', checkChanges);
    window.setInterval(checkChanges, 30_000);
  });
})();
