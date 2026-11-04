/** PreventiTI — Estado compartido, navegación e inicio de la interfaz. */

import './core/session-data.js';
import './core/api-client.js';
import './lib/report-history.js';
import './lib/inventory.js';

import { createDashboard } from './features/dashboard.js';
import { createCalendar } from './features/calendar.js';
import { createStatistics } from './features/statistics.js';
import { createInventory } from './features/inventory.js';
import { createReports } from './features/reports.js';
import { createMaintenance } from './features/maintenance.js';
import { createConsumables } from './features/consumables.js';
import { createUsers } from './features/users.js';
import { createInfrastructure } from './features/infrastructure.js';
import { initializeToasts } from './ui/toasts.js';
import { initializeModals } from './ui/modals.js';
import { createForms } from './ui/forms.js';
import { initializeAssetEditor } from './features/asset-editor.js';

const db = window.PreventiTIDB.load();
window.db = db;

function saveDB() {
  window.PreventiTIDB.save(db);
  window.db = db;
  renderCurrentRoute();
  updateGlobalBadges();
}

function getCurrentUser() {
  return window.currentUserProfile.user;
}

function addAuditLog(action, entity, detail) {
  const user = getCurrentUser();
  const now = new Date().toISOString().replace('T', ' ').substring(0, 19);
  db.auditLogs.unshift({
    id: Date.now(),
    timestamp: now,
    user_name: user.name,
    action: action,
    entity: entity,
    detail: detail
  });
}

const context = { db, getCurrentUser, saveDB, addAuditLog, getRoute, navigateTo, applySessionPermissions };

const { renderDashboardView } = createDashboard(context);
const { renderCalendarioView } = createCalendar(context);
const { renderEstadisticasView, renderAnalisisView } = createStatistics(context);
const { renderInventarioView } = createInventory(context);
const { renderReportsView } = createReports(context);
const { renderMantenimientosView } = createMaintenance(context);
const { renderConsumiblesView } = createConsumables(context);
const { renderUsuariosView } = createUsers(context);
const { renderInfraestructuraView } = createInfrastructure(context);
const { initFormHandlers, populateModalSelectors } = createForms(context);
initializeToasts();
initializeModals(context);

const routes = {
  'dashboard': renderDashboardView,
  'inventario': renderInventarioView,
  'reportes': renderReportsView,
  'mapa': renderMapaView,
  'calendario': renderCalendarioView,
  'estadisticas': renderEstadisticasView,
  'analisis': renderAnalisisView,
  'mantenimientos': renderMantenimientosView,
  'consumibles': renderConsumiblesView,
  'usuarios': renderUsuariosView,
  'infraestructura': renderInfraestructuraView
};

function getRoute() {
  const hash = window.location.hash.replace('#', '').trim();
  return routes[hash] ? hash : 'dashboard';
}

function navigateTo(route) {
  window.location.hash = route;
}

window.navigateTo = navigateTo;
window.renderCurrentRoute = renderCurrentRoute;

function renderCurrentRoute() {
  const route = getRoute();
  const user = getCurrentUser();
  if (!window.preventitiDataReady) {
    const content = document.getElementById('view-container');
    if (content) content.textContent = 'Cargando tu espacio de trabajo…';
    return;
  }

  // Verificación de autorización de navegación (Protección Frontend):
  // Si la ruta solicitada no está permitida para este usuario, bloquear y volver a dashboard
  if (window.db) {
    const area = { inventario: 'activos', analisis: 'estadisticas' }[route] || route;
    if (!window.db.allowedAreas.includes(area) && !window.db.allowedAreas.includes(route)) {
      console.warn(`[Router Guard] Acceso bloqueado a ruta #${route} para rol ${user.role}`);
      window.showToast("Acceso Restringido", `El módulo '${route}' no está disponible para su perfil (${user.role_title}).`, "warning");
      window.location.hash = 'dashboard';
      return;
    }
  }

  floorplanGeneration++;
  floorplanView?.dispose();
  floorplanView = null;
  updateActiveNav(route);
  const container = document.getElementById('view-container');
  if (container && routes[route]) {
    routes[route](container);
    applySessionPermissions();
    if (window.lucide) {
      window.lucide.createIcons();
    }
  }
  // Cerrar sidebar en móvil si estaba abierto
  closeMobileSidebar();
}

function closeMobileSidebar() {
  const sidebar = document.getElementById('app-sidebar');
  const backdrop = document.getElementById('sidebar-backdrop');
  if (window.innerWidth < 1024) {
    if (sidebar) sidebar.classList.add('-translate-x-full');
    if (backdrop) backdrop.classList.add('hidden');
  }
}

window.scrollToSection = function (id) {
  const section = document.getElementById(id);
  if (!section) return;
  section.focus({ preventScroll: true });
  section.scrollIntoView({ block: 'start', behavior: 'smooth' });
};

function updateActiveNav(route) {
  document.querySelectorAll('.nav-link').forEach(link => {
    const target = link.getAttribute('data-route');
    if (target === route) {
      link.classList.add('nav-item-active');
      link.setAttribute('aria-current', 'page');
      const group = link.closest('[data-nav-group]');
      const groupLabel = document.getElementById('page-context-group');
      const pageLabel = document.getElementById('page-context-title');
      if (groupLabel) groupLabel.textContent = group?.querySelector('.nav-section-title')?.textContent || '';
      if (pageLabel) pageLabel.textContent = link.querySelector('[data-nav-label]')?.textContent || '';
      link.classList.remove('text-slate-300', 'hover:bg-slate-900');
    } else {
      link.classList.remove('nav-item-active');
      link.removeAttribute('aria-current');
      link.classList.add('text-slate-300', 'hover:bg-slate-900');
    }
  });
}

function updateGlobalBadges() {
  const totalReports = (db.reports || []).length;
  const unreadNotifications = (db.notifications || []).filter(item => !item.read_at).length;
  const attentionAssets = window.PreventiTIInventory.selectAssets(db.assets, { status: 'atencion' }).length;
  const criticalAssets = window.PreventiTIInventory.selectAssets(db.assets, { status: 'critica' }).length;
  const totalCalendarTasks = db.calendarTasks ? db.calendarTasks.length : 19;

  const inventoryBadge = document.getElementById('badge-nav-inventario');
  if (inventoryBadge) inventoryBadge.textContent = db.assets.length;

  const reportBadge = document.getElementById('badge-nav-reportes');
  if (reportBadge) {
    reportBadge.textContent = totalReports;
    reportBadge.style.display = totalReports > 0 ? 'inline-flex' : 'none';
  }

  const notificationBadge = document.getElementById('badge-notificaciones');
  if (notificationBadge) {
    notificationBadge.textContent = unreadNotifications;
    notificationBadge.style.display = unreadNotifications > 0 ? 'inline-flex' : 'none';
  }

  const alertBadge = document.getElementById('badge-nav-alerts');
  if (alertBadge) {
    const totalAlerts = attentionAssets + criticalAssets;
    alertBadge.textContent = totalAlerts;
    alertBadge.style.display = totalAlerts > 0 ? 'inline-flex' : 'none';
  }

  const calBadge = document.getElementById('badge-nav-calendar');
  if (calBadge) {
    calBadge.textContent = totalCalendarTasks;
  }

  // Actualizar datos del usuario actual en el header
  const user = getCurrentUser();
  const userDisplay = document.getElementById('current-user-name');
  const userRoleDisplay = document.getElementById('current-user-role');
  const userAvatarDisplay = document.getElementById('current-user-avatar');
  if (userDisplay) userDisplay.textContent = user.name;
  if (userRoleDisplay) userRoleDisplay.textContent = `${user.role_title} (${user.role.toUpperCase()})`;
  if (userAvatarDisplay) userAvatarDisplay.textContent = user.avatar;


}

function applySessionPermissions() {
  const permissions = window.currentUserProfile.permissions;
  document.querySelectorAll('[data-permission]').forEach(element => { element.hidden = !permissions[element.dataset.permission]; });
  window.PreventiTIAPI.applySidebarPermissions(window.currentUserProfile.allowed_areas);
}
window.refreshSessionUI = function () {
  populateModalSelectors();
  updateGlobalBadges();
  renderCurrentRoute();
  applySessionPermissions();
};

// Inicialización de formularios, navegación y menú adaptable.
window.addEventListener('DOMContentLoaded', () => {
  populateModalSelectors();
  window.addEventListener('hashchange', renderCurrentRoute);
  renderCurrentRoute();
  updateGlobalBadges();
  initFormHandlers();
  initializeAssetEditor(context);
  applySessionPermissions();

  // Toggle de Sidebar Móvil y PC
  const btnMobileMenu = document.getElementById('btn-mobile-menu');
  const btnSidebarClose = document.getElementById('btn-sidebar-close');
  const sidebar = document.getElementById('app-sidebar');
  const backdrop = document.getElementById('sidebar-backdrop');

  if (btnMobileMenu && sidebar && backdrop) {
    btnMobileMenu.addEventListener('click', () => {
      sidebar.classList.remove('-translate-x-full');
      backdrop.classList.remove('hidden');
    });
  }

  if (btnSidebarClose && sidebar && backdrop) {
    btnSidebarClose.addEventListener('click', closeMobileSidebar);
  }

  if (backdrop) {
    backdrop.addEventListener('click', closeMobileSidebar);
  }
});

let floorplanView = null;
let floorplanGeneration = 0;

function renderMapaView(container) {
  const generation = ++floorplanGeneration;
  container.innerHTML = '<div class="fp-empty">Cargando mapa 3D…</div>';
  import('./floorplan/index.js').then(({ mountFloorplan }) => {
    if (generation !== floorplanGeneration || getRoute() !== 'mapa') return;
    floorplanView = mountFloorplan(container);
  }).catch(error => {
    if (generation !== floorplanGeneration) return;
    container.textContent = 'No se pudo cargar el mapa 3D. Recarga la página para reintentar.';
    console.error(error);
  });
}
