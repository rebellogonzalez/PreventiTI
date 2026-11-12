<!DOCTYPE html>
<html lang="es" class="h-full bg-slate-950">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Iniciar Sesión — PreventiTI</title>
  <link rel="stylesheet" href="{{ asset('css/tailwind.css') }}?v={{ filemtime(public_path('css/tailwind.css')) }}">
  <link rel="stylesheet" href="{{ asset('css/fonts.css') }}">
  <script src="{{ asset('js/vendor/lucide/lucide-1.61.0.min.js') }}"></script>
  <style>
    body { font-family: 'Plus Jakarta Sans', sans-serif; }
    .font-mono { font-family: 'JetBrains Mono', monospace; }
  </style>
</head>
<body class="min-h-full flex flex-col justify-center py-6 sm:py-12 sm:px-6 lg:px-8 bg-gradient-to-br from-slate-950 via-slate-900 to-sky-950 text-slate-100">

  <div class="sm:mx-auto sm:w-full sm:max-w-md text-center">
    <!-- Logotipo -->
    <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-gradient-to-tr from-sky-600 to-indigo-500 shadow-lg shadow-sky-500/20 mb-4">
      <i data-lucide="shield-alert" class="w-8 h-8 text-white"></i>
    </div>
    <h2 class="text-3xl font-extrabold tracking-tight text-white">
      Preventi<span class="text-sky-400">TI</span>
    </h2>
    <p class="mt-1 text-xs text-slate-400 font-medium">
      Gestión Preventiva de Activos &bull; Control de Incidencias &bull; Laboratorio Red
    </p>
  </div>

  <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md px-4 sm:px-0">
    <div class="bg-slate-900/90 backdrop-blur-xl py-8 px-6 sm:px-10 shadow-2xl rounded-2xl border border-slate-800">
      
      @if ($errors->any())
        <div class="mb-5 p-3 rounded-lg bg-rose-500/10 border border-rose-500/30 text-rose-300 text-xs flex items-center gap-2">
          <i data-lucide="alert-circle" class="w-4 h-4 flex-shrink-0 text-rose-400"></i>
          <span>{{ $errors->first() }}</span>
        </div>
      @endif

      <form action="{{ route('login') }}" method="POST" class="space-y-4">
        @csrf

        <div>
          <label for="email" class="block text-xs font-semibold text-slate-300 mb-1">Correo Institucional</label>
          <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500">
              <i data-lucide="mail" class="w-4 h-4"></i>
            </div>
            <input id="email" name="email" type="email" autocomplete="email" required
                   value="{{ old('email') }}"
                   placeholder="usuario@preventiti.local"
                   class="block w-full pl-9 pr-3 py-2.5 bg-slate-950/60 border border-slate-700 rounded-lg text-xs text-white placeholder-slate-500 focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 transition">
          </div>
        </div>

        <div>
          <label for="password" class="block text-xs font-semibold text-slate-300 mb-1">Contraseña de Acceso</label>
          <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500">
              <i data-lucide="lock" class="w-4 h-4"></i>
            </div>
            <input id="password" name="password" type="password" autocomplete="current-password" required
                   placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;"
                   class="block w-full pl-9 pr-3 py-2.5 bg-slate-950/60 border border-slate-700 rounded-lg text-xs text-white placeholder-slate-500 focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 transition">
          </div>
        </div>

        <div class="flex items-center justify-between text-xs pt-1">
          <label class="flex items-center gap-2 text-slate-400 cursor-pointer">
            <input type="checkbox" name="remember" class="w-4 h-4 rounded bg-slate-950 border-slate-700 text-sky-600 focus:ring-sky-500">
            <span>Recordar sesión</span>
          </label>
          <span class="text-slate-500 text-[11px]">Seguridad RBAC v2.0</span>
        </div>

        <button type="submit"
                class="w-full mt-2 py-2.5 px-4 bg-sky-600 hover:bg-sky-500 text-white font-semibold text-xs rounded-lg shadow-lg shadow-sky-600/30 transition flex items-center justify-center gap-2">
          <i data-lucide="log-in" class="w-4 h-4"></i>
          <span>Ingresar a PreventiTI</span>
        </button>
      </form>


    </div>

    <div class="mt-6 text-center text-[11px] text-slate-500">
      PreventiTI &bull; Acceso personal con correo y contraseña
    </div>
  </div>

  <script>
    lucide.createIcons();

  </script>
</body>
</html>
