<!DOCTYPE html>
<html lang="es" class="h-full bg-slate-50">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>PreventiTI — Gestión Preventiva de Activos Tecnológicos</title>
  
  <link rel="stylesheet" href="{{ asset('css/tailwind.css') }}?v={{ filemtime(public_path('css/tailwind.css')) }}">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <script>window.currentUserProfile = @json($sessionProfile);</script>
  <style>[hidden] { display: none !important; }</style>
  <script src="{{ asset('js/vendor/lucide/lucide-1.61.0.min.js') }}"></script>
  <script src="{{ asset('js/vendor/chart/chart-4.6.1.min.js') }}"></script>

  <!-- Custom CSS -->
  <link rel="stylesheet" href="{{ asset('css/styles.css') }}">
  <link rel="stylesheet" href="{{ asset('css/map-styles.css') }}">
  <link rel="stylesheet" href="{{ asset('css/technician-history.css') }}">
</head>
<body class="h-full flex overflow-hidden">

  @include('app.partials.sidebar')

  <!-- CONTENEDOR PRINCIPAL DE CONTENIDO -->
  <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
    
    @include('app.partials.header')

    <!-- ÁREA DE VISTA DINÁMICA SPA -->
    <main id="app-main" class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8 bg-slate-50">
      <nav class="page-context max-w-7xl mx-auto" aria-label="Ubicación actual">
        <span id="page-context-group">Vista general</span>
        <span class="page-context-divider" aria-hidden="true">/</span>
        <span id="page-context-title" aria-current="page">Panel principal</span>
      </nav>
      <div id="view-container" class="max-w-7xl mx-auto">
        <!-- Renderizado dinámico de rutas -->
      </div>
    </main>
  </div>

  <!-- ======================================================================= -->
  <!-- MODALES DE LA APLICACIÓN                                                -->
  <!-- ======================================================================= -->

  @include('app.modals.report')

  @include('app.modals.maintenance')

  @include('app.modals.consumable')

  @include('app.modals.asset-create')

  @include('app.modals.asset-detail')

  @include('app.modals.calendar-task')

  <!-- Contenedor Flotante de Toasts -->
  <div id="toast-container"></div>

  <!-- Scripts de la Aplicación -->
  <script type="module" src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}"></script>
</body>
</html>
