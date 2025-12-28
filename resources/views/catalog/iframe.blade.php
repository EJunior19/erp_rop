{{-- resources/views/catalogo/iframe.blade.php --}}
@extends('layout.admin')

@section('content')
<div class="w-full px-6">
  <div class="flex items-center justify-between mb-4">
    <div>
      <h1 class="text-2xl font-bold text-emerald-400 flex items-center gap-2">
        🛒 Catálogo Inteligente (embed)
      </h1>
      <p class="text-sm text-gray-400">
        Navegá y scrolleá el catálogo desde acá mismo.
      </p>
    </div>

    <a href="{{ config('services.catalogo_inteligente.url', 'http://158.220.120.146:8082/productos') }}"
       target="_blank" rel="noopener"
       class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-semibold shadow">
      🔗 Abrir en nueva pestaña
    </a>
  </div>

  <div class="rounded-2xl border border-gray-800 bg-gray-950 overflow-hidden shadow-xl">
    <iframe
      src="{{ config('services.catalogo_inteligente.url', 'http://158.220.120.146:8082/productos') }}"
      class="w-full"
      style="height: calc(100vh - 220px); border: 0;"
      loading="lazy"
      referrerpolicy="no-referrer"
      allow="clipboard-read; clipboard-write; fullscreen"
    ></iframe>
  </div>

  <div class="mt-3 text-xs text-gray-500">
    Si ves pantalla negra o “refused to connect”, es bloqueo de iframe (X-Frame-Options / CSP) en el catálogo inteligente.
  </div>
</div>
@endsection
