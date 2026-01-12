{{-- resources/views/layout/admin.blade.php --}}
<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="csrf-token" content="{{ csrf_token() }}">

  <title>@yield('title','Dashboard · CRM Katuete')</title>

  {{-- App styles/scripts (Vite) --}}
  @vite(['resources/css/app.css','resources/js/app.js'])

  {{-- Hooks por página --}}
  @stack('head')
  @stack('meta')
  @stack('styles')
</head>

<body
  x-data="{ sidebarOpen: false }"
  class="h-full overflow-hidden bg-[#0b0d10] text-gray-100 flex flex-col"
>
  {{-- ===== Topbar ===== --}}
  <header class="z-40 bg-[#0f1114] border-b border-gray-700 shadow-sm flex-shrink-0">
    <nav class="flex items-center justify-between px-4 py-3">
      <div class="flex items-center gap-3">
        {{-- Hamburguesa (móvil) --}}
        <button
          type="button"
          class="md:hidden inline-flex items-center justify-center rounded-md p-2 text-gray-400 hover:text-gray-100 hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-sky-500"
          @click="sidebarOpen = true"
          aria-controls="sidebarScroll"
          :aria-expanded="sidebarOpen"
        >
          <span class="sr-only">Abrir menú</span>
          <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M4 6h16M4 12h16M4 18h16"/>
          </svg>
        </button>

        <a href="{{ route('dashboard.index') }}" class="font-bold text-lg text-gray-100">
          ERP Roperia        </a>

        {{-- Breadcrumbs --}}
        @hasSection('breadcrumbs')
          <div class="hidden md:block border-l border-gray-700 pl-3 ml-3 text-sm text-gray-400">
            @yield('breadcrumbs')
          </div>
        @endif
      </div>

      {{-- Buscador --}}
      <form class="hidden md:block w-1/3" role="search" method="GET" action="{{ url('/dashboard') }}">
        <div class="flex">
          <input
            type="text"
            name="q"
            placeholder="Buscar…"
            class="w-full rounded-l-md bg-[#0b0d10] border border-gray-700 text-sm text-gray-100 placeholder-gray-500 focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
          />
          <button type="submit" class="bg-sky-600 hover:bg-sky-500 text-white px-3 rounded-r-md">
            <span class="sr-only">Buscar</span>
            <i class="fas fa-search"></i>
          </button>
        </div>
      </form>

      {{-- Acciones derechas --}}
      <div class="hidden md:flex items-center gap-2">
        @yield('toolbar')
      </div>
    </nav>
  </header>

  {{-- ===== Wrapper principal ===== --}}
  <div class="flex flex-1 min-h-0 overflow-hidden">
    {{-- ===== Sidebar ===== --}}
    <div class="relative z-30 flex-shrink-0" x-cloak>
      {{-- Overlay móvil --}}
      <div
        class="fixed inset-0 bg-black/50 md:hidden"
        x-show="sidebarOpen"
        x-transition.opacity
        @click="sidebarOpen=false"
        aria-hidden="true"
      ></div>

      {{-- Sidebar --}}
      <aside
        id="sidebarScroll"
        class="fixed md:static inset-y-0 left-0 w-72 md:w-64 bg-[#0f1114]
               md:border-r md:border-gray-700
               transform md:transform-none
               transition-transform md:transition-none
               h-full overflow-y-auto overscroll-contain"
        :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0'"
        x-trap.noscroll.inert="sidebarOpen"
        @keydown.escape.window="sidebarOpen=false"
        aria-label="Menú lateral"
      >
        <div
          class="h-full"
          @click="if ($event.target.closest('a')) sidebarOpen = false"
        >
          @include('layout.menu')
        </div>
      </aside>
    </div>

    {{-- ===== Columna derecha ===== --}}
    <div class="flex-1 flex flex-col min-h-0">
      {{-- Contenido --}}
      <main class="flex-1 min-h-0 overflow-hidden bg-[#0b0d10]">

        {{-- CONTENEDOR REAL DE SCROLL --}}
        <div class="h-full overflow-y-auto p-6">
          <x-flash-message />
          @yield('content')
        </div>

      </main>


      {{-- Footer --}}
      <footer class="bg-[#0f1114] border-t border-gray-700 py-4 px-6 text-sm text-gray-400 flex flex-col md:flex-row md:items-center md:justify-between gap-2 flex-shrink-0">
        <div>© {{ date('Y') }} CRM Katuete</div>
        <div class="space-x-3">
          <a href="javascript:void(0)" class="hover:text-gray-200">Política de Privacidad</a>
          <span aria-hidden="true">&middot;</span>
          <a href="javascript:void(0)" class="hover:text-gray-200">Términos y Condiciones</a>
        </div>
      </footer>
    </div>
  </div>

  {{-- Alpine.js --}}
  <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

  {{-- SweetAlert2 --}}
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  {{-- Persistir scroll del sidebar --}}
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const el = document.getElementById('sidebarScroll');
      if (!el) return;

      const key = 'sidebarScrollTop';
      const saved = sessionStorage.getItem(key);
      if (saved !== null) el.scrollTop = parseInt(saved, 10);

      el.addEventListener('scroll', () => {
        sessionStorage.setItem(key, String(el.scrollTop));
      }, { passive: true });

      el.querySelectorAll('a[href]').forEach(a => {
        a.addEventListener('click', () => {
          sessionStorage.setItem(key, String(el.scrollTop));
        });
      });
    });
  </script>

  {{-- Confirmación global --}}
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      document.body.addEventListener('submit', (e) => {
        const form = e.target.closest('form');
        if (!form) return;

        const needsConfirm = form.classList.contains('delete-form') || form.dataset.confirm;
        if (!needsConfirm) return;

        e.preventDefault();

        const name = form.getAttribute('data-name') || form.dataset.name || 'este registro';
        const text = form.dataset.confirm || `Se eliminará ${name}. Esta acción no se puede deshacer.`;

        Swal.fire({
          title: '¿Estás seguro?',
          text,
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#d33',
          cancelButtonColor: '#3085d6',
          confirmButtonText: 'Sí, eliminar',
          cancelButtonText: 'Cancelar'
        }).then((result) => {
          if (result.isConfirmed) form.submit();
        });
      });
    });
  </script>

  @stack('scripts')
</body>
</html>
