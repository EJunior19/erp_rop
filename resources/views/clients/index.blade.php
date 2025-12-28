@extends('layout.admin')

@section('content')
<div class="w-full px-6">

  {{-- TÍTULO --}}
  <div class="flex items-center gap-2 mb-4">
    <h1 class="text-3xl font-bold text-green-400">👥 Lista de Clientes</h1>
  </div>

  {{-- BOTÓN + FILTROS (FIJOS, NO SCROLL) --}}
  <div class="mb-6 space-y-3">

    <div class="flex justify-start">
      <a href="{{ route('clients.create') }}"
         class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded">
        + Nuevo Cliente
      </a>
    </div>

    <form method="GET" action="{{ route('clients.index') }}"
          class="w-full bg-[#0f172a] text-white border border-green-700/30 rounded-xl p-4">
      @php
        $status  = request('status','all');
        $test    = request('test','all');
        $per     = (int) request('per_page', 25);
      @endphp

      <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">

        {{-- Buscar --}}
        <div class="md:col-span-5">
          <label class="block text-sm text-green-300 mb-1">Buscar</label>
          <input type="text" name="q" value="{{ request('q') }}"
                 placeholder="Nombre, email, teléfono, código, RUC…"
                 class="w-full bg-gray-800 border border-gray-700 rounded px-3 py-2 text-white placeholder-gray-400 focus:ring-2 focus:ring-green-600">
        </div>

        {{-- Estado --}}
        <div class="md:col-span-2">
          <label class="block text-sm text-green-300 mb-1">Estado</label>
          <select name="status"
                  class="w-full bg-gray-800 border border-gray-700 rounded px-3 py-2 focus:ring-2 focus:ring-green-600">
            <option value="all"      @selected($status==='all')>Todos</option>
            <option value="active"   @selected($status==='active')>Activos</option>
            <option value="inactive" @selected($status==='inactive')>Inactivos</option>
          </select>
        </div>

        {{-- Tipo --}}
        <div class="md:col-span-2">
          <label class="block text-sm text-green-300 mb-1">Tipo</label>
          <select name="test"
                  class="w-full bg-gray-800 border border-gray-700 rounded px-3 py-2 focus:ring-2 focus:ring-green-600">
            <option value="all"  @selected($test==='all')>Todos</option>
            <option value="prod" @selected($test==='prod')>Producción</option>
            <option value="test" @selected($test==='test')>Prueba</option>
          </select>
        </div>

        {{-- Por página --}}
        <div class="md:col-span-1">
          <label class="block text-sm text-green-300 mb-1">Pág.</label>
          <select name="per_page"
                  class="w-full bg-gray-800 border border-gray-700 rounded px-3 py-2 focus:ring-2 focus:ring-green-600">
            @foreach([10,25,50,100] as $n)
              <option value="{{ $n }}" @selected($per===$n)>{{ $n }}</option>
            @endforeach
          </select>
        </div>

        {{-- Botones --}}
        <div class="md:col-span-2 flex gap-2">
          <button type="submit"
                  class="w-full bg-green-600 hover:bg-green-700 text-white rounded px-4 py-2">
            🔎 Buscar
          </button>
          <a href="{{ route('clients.index') }}"
             class="w-full bg-gray-700 hover:bg-gray-600 text-white rounded px-4 py-2 text-center">
            ✖ Limpiar
          </a>
        </div>

      </div>
    </form>
  </div>

  {{-- FLASH --}}
  @if(session('success'))
    <x-flash-message type="success" :message="session('success')" />
  @endif

  {{-- TABLA (SOLO ESTO SCROLLEA) --}}
  <div class="bg-gray-900 text-white rounded-xl shadow-2xl border-2 border-green-400 overflow-hidden">

    {{-- CONTENEDOR SCROLL --}}
    <div class="max-h-[65vh] overflow-y-auto">
      <table class="w-full text-sm text-left">

        <thead class="bg-gray-800 text-green-300 sticky top-0 z-20">
          <tr>
            <th class="px-6 py-3">ID</th>
            <th class="px-6 py-3">Código</th>
            <th class="px-6 py-3">Nombre</th>
            <th class="px-6 py-3">Email</th>
            <th class="px-6 py-3">Teléfono</th>
            <th class="px-6 py-3">Estado</th>
            <th class="px-6 py-3">Tipo</th>
            <th class="px-6 py-3 text-center">Acciones</th>
          </tr>
        </thead>

        <tbody class="divide-y divide-gray-800">
          @forelse($clients as $c)
            <tr class="hover:bg-gray-800/60 transition">
              <td class="px-6 py-3">{{ $c->id }}</td>
              <td class="px-6 py-3">{{ $c->code }}</td>
              <td class="px-6 py-3">
                <div class="flex flex-col">
                  <span>{{ $c->name }}</span>
                  @if($c->ruc)
                    <span class="text-xs text-gray-400">RUC: {{ $c->ruc }}</span>
                  @endif
                </div>
              </td>
              <td class="px-6 py-3">{{ $c->email }}</td>
              <td class="px-6 py-3">{{ $c->phone ?? '—' }}</td>
              <td class="px-6 py-3">
                @if($c->active)
                  <span class="px-2 py-0.5 text-xs rounded bg-emerald-900 text-emerald-200 border border-emerald-700">Activo</span>
                @else
                  <span class="px-2 py-0.5 text-xs rounded bg-red-900 text-red-200 border border-red-700">Inactivo</span>
                @endif
              </td>
              <td class="px-6 py-3">
                @if($c->is_test)
                  <span class="px-2 py-0.5 text-xs rounded bg-yellow-900 text-yellow-200 border border-yellow-700">Prueba</span>
                @else
                  <span class="px-2 py-0.5 text-xs rounded bg-blue-900 text-blue-200 border border-blue-700">Producción</span>
                @endif
              </td>
              <td class="px-6 py-3">
                <div class="flex justify-center gap-2">
                  <x-action-buttons 
                    :show="route('clients.show',$c)" 
                    :edit="route('clients.edit',$c)" 
                    :delete="route('clients.destroy',$c)" 
                    :name="'el cliente '.$c->name" />
                </div>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="8" class="px-6 py-8 text-center text-gray-400">
                No se encontraron clientes con los filtros aplicados.
              </td>
            </tr>
          @endforelse
        </tbody>

      </table>
    </div>

    {{-- PAGINACIÓN (FIJA) --}}
    <div class="px-4 py-3 border-t border-gray-800">
      {{ $clients->appends(request()->query())->links() }}
    </div>

  </div>
</div>
@endsection
