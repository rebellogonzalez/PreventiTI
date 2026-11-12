    <!-- TOPBAR SUPERIOR -->
    <header class="min-h-16 bg-white border-b border-slate-200 px-4 py-2 sm:px-6 flex items-center justify-between z-10 flex-shrink-0">
      <div class="flex items-center gap-3">
        <!-- Botón Menú Móvil -->
        <button id="btn-mobile-menu" aria-label="Abrir menú" class="lg:hidden p-2 rounded-lg text-slate-500 hover:bg-slate-100">
          <i data-lucide="menu" class="w-5 h-5"></i>
        </button>

        <!-- Referencia de topología; no representa una medición de conectividad -->
        <div class="hidden sm:flex items-center gap-2 px-2.5 py-1 rounded-full bg-slate-100 text-[11px] text-slate-600 font-medium">
          <i data-lucide="network" class="w-3 h-3"></i>
          <span>Topología de referencia: VLAN 10 &bull; VLAN 20</span>
        </div>
      </div>

      <!-- Información de la sesión -->
      <div class="min-w-0 flex items-center justify-end gap-2 pl-2 sm:gap-3">
        <span class="min-w-0 break-all text-right text-xs text-slate-600">{{ $sessionProfile['user']['email'] }}</span>

        <!-- Botón Notificaciones -->
        <button data-permission="can_view_notifications" onclick="window.openNotifications()" aria-label="Ver notificaciones" class="shrink-0 p-2 text-slate-500 hover:text-slate-700 hover:bg-slate-100 rounded-lg relative">
          <i data-lucide="bell" class="w-4 h-4"></i>
          <span id="badge-notificaciones" class="hidden min-w-4 h-4 px-1 items-center justify-center bg-amber-500 text-white text-[9px] font-bold rounded-full absolute top-0 right-0">0</span>
        </button>
      </div>
    </header>
