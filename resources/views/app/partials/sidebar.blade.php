  <!-- Backdrop para menú móvil -->
  <div id="sidebar-backdrop" class="fixed inset-0 z-40 bg-slate-900/50 backdrop-blur-sm hidden lg:hidden transition-opacity"></div>

  <!-- SIDEBAR LATERAL NAVEGABLE -->
  <aside id="app-sidebar" class="fixed inset-y-0 left-0 z-50 w-64 bg-slate-950 text-white flex flex-col transition-transform duration-300 transform -translate-x-full lg:translate-x-0 lg:static lg:inset-0 border-r border-slate-800">
    <!-- Header del Sidebar / Brand -->
    <div class="h-16 px-5 flex items-center justify-between border-b border-slate-800/80 bg-slate-950">
      <div class="flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg bg-sky-600 flex items-center justify-center text-white shadow-md shadow-sky-600/30">
          <i data-lucide="shield-alert" class="w-5 h-5"></i>
        </div>
        <div>
          <span class="text-base font-bold tracking-tight text-white flex items-center gap-1.5">
            Preventi<span class="text-sky-400">TI</span>
          </span>
          <span class="text-[10px] text-slate-400 block -mt-0.5 font-medium">Gestión Preventiva</span>
        </div>
      </div>
      <div class="flex items-center gap-1">
        <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-sky-950 text-sky-300 border border-sky-800">MVP</span>
        <button id="btn-sidebar-close" aria-label="Cerrar menú" class="lg:hidden text-slate-400 hover:text-white p-1 rounded-lg ml-1">
          <i data-lucide="x" class="w-5 h-5"></i>
        </button>
      </div>
    </div>

    <!-- Menú de Navegación SPA -->
    <nav class="app-navigation flex-1 min-h-0 overflow-y-auto" aria-label="Navegación principal">
      <section class="nav-section" data-nav-group="resumen" aria-labelledby="nav-group-resumen">
        <h2 id="nav-group-resumen" class="nav-section-title">Vista general</h2>
        @if(in_array('dashboard', $sessionProfile['allowed_areas']))
<a href="#dashboard" data-route="dashboard" class="nav-link flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium text-slate-300 hover:bg-slate-900 transition group">
        <div class="flex items-center gap-2.5">
          <i data-lucide="layout-dashboard" class="w-4 h-4 text-slate-400 group-hover:text-white"></i>
          <span data-nav-label>Panel principal</span>
        </div>
        <span id="badge-nav-alerts" class="px-1.5 py-0.2 rounded-full text-[10px] font-bold bg-amber-500/20 text-amber-300 border border-amber-500/30 hidden">0</span>
      </a>
@endif
      </section>
      <section class="nav-section" data-nav-group="dispositivos" aria-labelledby="nav-group-dispositivos">
        <h2 id="nav-group-dispositivos" class="nav-section-title">Dispositivos</h2>
        @if(in_array('activos', $sessionProfile['allowed_areas']))
<a href="#inventario" data-route="inventario" class="nav-link flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium text-slate-300 hover:bg-slate-900 transition group">
        <div class="flex items-center gap-2.5">
          <i data-lucide="boxes" class="w-4 h-4 text-slate-400 group-hover:text-white"></i>
          <span data-nav-label>Inventario</span>
        </div>
        <span id="badge-nav-inventario" class="px-1.5 py-0.2 rounded text-[10px] font-semibold bg-slate-800 text-slate-400">0</span>
      </a>
@endif
        @if(in_array('mapa', $sessionProfile['allowed_areas']))
<a href="#mapa" data-route="mapa" class="nav-link flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium text-slate-300 hover:bg-slate-900 transition group">
        <div class="flex items-center gap-2.5">
          <i data-lucide="map" class="w-4 h-4 text-slate-400 group-hover:text-white"></i>
          <span data-nav-label>Mapa de dispositivos</span>
        </div>
      </a>
@endif
      </section>
      <section class="nav-section" data-nav-group="operacion" aria-labelledby="nav-group-operacion">
        <h2 id="nav-group-operacion" class="nav-section-title">Operación diaria</h2>
        @if(in_array('reportes', $sessionProfile['allowed_areas']))
<a href="#reportes" data-route="reportes" class="nav-link flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium text-slate-300 hover:bg-slate-900 transition group">
        <div class="flex items-center gap-2.5">
          <i data-lucide="file-text" class="w-4 h-4 text-slate-400 group-hover:text-white"></i>
          <span data-nav-label>Reportes</span>
        </div>
        <span id="badge-nav-reportes" class="px-1.5 py-0.2 rounded-full text-[10px] font-bold bg-sky-500 text-white hidden">0</span>
      </a>
@endif
        @if(in_array('calendario', $sessionProfile['allowed_areas']))
<a href="#calendario" data-route="calendario" class="nav-link flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium text-slate-300 hover:bg-slate-900 transition group">
        <div class="flex items-center gap-2.5">
          <i data-lucide="calendar" class="w-4 h-4 text-slate-400 group-hover:text-white"></i>
          <span data-nav-label>Calendario de tareas</span>
        </div>
        <span id="badge-nav-calendar" class="px-1.5 py-0.2 rounded text-[10px] font-semibold bg-emerald-950 text-emerald-300 border border-emerald-800">19</span>
      </a>
@endif
        @if(in_array('mantenimientos', $sessionProfile['allowed_areas']))
<a href="#mantenimientos" data-route="mantenimientos" class="nav-link flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium text-slate-300 hover:bg-slate-900 transition group">
        <i data-lucide="calendar-clock" class="w-4 h-4 text-slate-400 group-hover:text-white"></i>
        <span data-nav-label>Mantenimientos</span>
      </a>
@endif
        @if(in_array('consumibles', $sessionProfile['allowed_areas']))
<a href="#consumibles" data-route="consumibles" class="nav-link flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium text-slate-300 hover:bg-slate-900 transition group">
        <i data-lucide="droplet" class="w-4 h-4 text-slate-400 group-hover:text-white"></i>
        <span data-nav-label>Insumos y consumibles</span>
      </a>
@endif
      </section>
      <section class="nav-section" data-nav-group="analitica" aria-labelledby="nav-group-analitica">
        <h2 id="nav-group-analitica" class="nav-section-title">Análisis y costos</h2>
        @if(in_array('estadisticas', $sessionProfile['allowed_areas']))
<a href="#analisis" data-route="analisis" class="nav-link flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium text-slate-300 hover:bg-slate-900 transition group">
        <i data-lucide="activity" class="w-4 h-4 text-slate-400 group-hover:text-white"></i>
        <span data-nav-label>Incidencias y repuestos</span>
      </a>
@endif
        @if(in_array('estadisticas', $sessionProfile['allowed_areas']))
<a href="#estadisticas" data-route="estadisticas" class="nav-link flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium text-slate-300 hover:bg-slate-900 transition group">
        <div class="flex items-center gap-2.5">
          <i data-lucide="bar-chart-3" class="w-4 h-4 text-slate-400 group-hover:text-white"></i>
          <span data-nav-label>Estadísticas y costos</span>
        </div>
        <span class="px-1.5 py-0.2 rounded text-[10px] font-semibold bg-sky-950 text-sky-400 border border-sky-800">BI</span>
      </a>
@endif
      </section>
      <section class="nav-section" data-nav-group="administracion" aria-labelledby="nav-group-administracion">
        <h2 id="nav-group-administracion" class="nav-section-title">Administración</h2>
        @if(in_array('usuarios', $sessionProfile['allowed_areas']))
<a href="#usuarios" data-route="usuarios" class="nav-link flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium text-slate-300 hover:bg-slate-900 transition group">
        <div class="flex items-center gap-2.5">
          <i data-lucide="users" class="w-4 h-4 text-slate-400 group-hover:text-white"></i>
          <span data-nav-label>Usuarios y roles</span>
        </div>
        <span class="px-1.5 py-0.2 rounded text-[10px] font-semibold bg-slate-800 text-slate-400">40</span>
      </a>
@endif
        @if(in_array('infraestructura', $sessionProfile['allowed_areas']))
<a href="#infraestructura" data-route="infraestructura" class="nav-link flex items-center gap-2.5 px-3 py-2 rounded-lg text-xs font-medium text-slate-300 hover:bg-slate-900 transition group">
        <i data-lucide="network" class="w-4 h-4 text-slate-400 group-hover:text-white"></i>
        <span data-nav-label>Infraestructura y respaldo</span>
      </a>
@endif
      </section>
    </nav>

    <!-- Sesión autenticada -->
    <div class="p-3 border-t border-slate-800 bg-slate-950/90 space-y-2">
      <!-- Usuario autenticado -->
      <div class="p-2.5 rounded-lg bg-slate-900 border border-slate-800 flex items-center gap-2.5">
        <div id="current-user-avatar" class="w-8 h-8 rounded-full bg-sky-600 text-white font-bold text-xs flex items-center justify-center flex-shrink-0 shadow-sm">
          {{ $sessionProfile['user']['avatar'] }}
        </div>
        <div class="min-w-0 flex-1">
          <div id="current-user-name" class="text-xs font-semibold text-white truncate">{{ $sessionProfile['user']['name'] }}</div>
          <div id="current-user-role" class="text-[10px] text-slate-400 truncate">{{ $sessionProfile['user']['role_title'] }}</div>
        </div>
      </div>

      <div class="flex flex-col gap-1.5 pt-1">
        <form action="{{ route('logout') }}" method="POST" class="w-full">
          @csrf
          <button type="submit" title="Cerrar la sesión actual" class="w-full text-center py-1.5 px-2 rounded bg-rose-950/40 hover:bg-rose-900/60 border border-rose-800/40 text-[11px] font-semibold text-rose-300 hover:text-white transition flex items-center justify-center gap-1.5">
            <i data-lucide="log-out" class="w-3.5 h-3.5"></i> Cerrar Sesión
          </button>
        </form>
      </div>
    </div>
  </aside>
