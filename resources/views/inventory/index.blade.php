{{-- resources/views/inventory/index.blade.php --}}
@extends('layout.admin')

@section('content')

{{-- ================= HEADER ================= --}}
<div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between mb-6">
  <div>
    <h1 class="text-2xl font-bold text-emerald-400 flex items-center gap-2">
      📦 Movimientos de Inventario
    </h1>
    <p class="text-sm text-gray-400 mt-1">
      Historial de entradas y salidas registradas (con filtros y exportación).
    </p>
  </div>

  <div class="flex flex-wrap gap-2">
    <a href="{{ route('dashboard.index') }}"
       class="inline-flex items-center gap-2 px-4 py-2 bg-gray-800 text-gray-200 rounded-lg border border-gray-700 hover:bg-gray-700 transition">
      ⬅️ Volver
    </a>

    <a href="{{ request()->fullUrl() }}"
       class="inline-flex items-center gap-2 px-4 py-2 bg-gray-800 text-gray-200 rounded-lg border border-gray-700 hover:bg-gray-700 transition">
      🔄 Refrescar
    </a>

    <x-create-button route="{{ route('inventory.create') }}" text="Nuevo movimiento" />
  </div>
</div>

{{-- Mensajes flash --}}
<x-flash-message />

{{-- ================= FILTROS ================= --}}
@php
  $q     = request('q', '');
  $type  = request('type', '');
  $from  = request('from', '');
  $to    = request('to', '');
@endphp

<form method="GET"
      class="bg-gray-950 rounded-xl border border-gray-800/60 shadow-lg p-4 mb-4">
  <div class="grid grid-cols-1 md:grid-cols-5 gap-3 items-end">

    {{-- Buscar --}}
    <div class="md:col-span-2">
      <label class="block text-xs font-semibold uppercase text-gray-400 mb-1">Buscar</label>
      <input type="text" name="q" value="{{ $q }}"
             placeholder="Producto, razón, usuario…"
             class="w-full rounded-lg bg-gray-900 border border-gray-700 text-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-600/40">
    </div>

    {{-- Tipo --}}
    <div>
      <label class="block text-xs font-semibold uppercase text-gray-400 mb-1">Tipo</label>
      <select name="type"
              class="w-full rounded-lg bg-gray-900 border border-gray-700 text-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-600/40">
        <option value="">Todos</option>
        <option value="entrada" {{ $type === 'entrada' ? 'selected' : '' }}>Entrada</option>
        <option value="salida"  {{ $type === 'salida' ? 'selected' : '' }}>Salida</option>
        <option value="ajuste"  {{ $type === 'ajuste' ? 'selected' : '' }}>Ajuste</option>
      </select>
    </div>

    {{-- Desde --}}
    <div>
      <label class="block text-xs font-semibold uppercase text-gray-400 mb-1">Desde</label>
      <input type="date" name="from" value="{{ $from }}"
             class="w-full rounded-lg bg-gray-900 border border-gray-700 text-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-600/40">
    </div>

    {{-- Hasta --}}
    <div>
      <label class="block text-xs font-semibold uppercase text-gray-400 mb-1">Hasta</label>
      <input type="date" name="to" value="{{ $to }}"
             class="w-full rounded-lg bg-gray-900 border border-gray-700 text-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-600/40">
    </div>

  </div>

  <div class="flex flex-wrap gap-2 mt-4">
    <button
      class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-semibold shadow shadow-emerald-600/20">
      🔎 Buscar
    </button>

    <a href="{{ route('inventory.index') }}"
       class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-gray-800 hover:bg-gray-700 border border-gray-700 text-gray-200">
      ✖ Limpiar
    </a>

    {{-- Export CSV simple (mismo endpoint con flag) --}}
    <a href="{{ route('inventory.index', array_merge(request()->query(), ['export' => 'csv'])) }}"
       class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-sky-600 hover:bg-sky-700 text-white font-semibold shadow shadow-sky-600/20">
      📤 Exportar CSV
    </a>

    <div class="ml-auto text-xs text-gray-400 flex items-center gap-2">
      <span class="px-2 py-1 rounded bg-gray-900 border border-gray-800">
        Registros: <span class="text-gray-200 font-semibold">{{ $movements->total() }}</span>
      </span>
      <span class="px-2 py-1 rounded bg-gray-900 border border-gray-800">
        Página: <span class="text-gray-200 font-semibold">{{ $movements->currentPage() }}</span>/<span class="text-gray-300">{{ $movements->lastPage() }}</span>
      </span>
    </div>
  </div>
</form>

{{-- ================= TABLA ================= --}}
<div class="bg-gray-950 rounded-xl shadow-lg border border-gray-800/60 overflow-hidden">
  <div class="overflow-x-auto">
    <table class="min-w-full text-sm text-left">
      <thead class="bg-gray-900 text-gray-300 uppercase text-xs tracking-wide">
        <tr>
          <th class="px-4 py-3">Fecha</th>
          <th class="px-4 py-3">Producto</th>
          <th class="px-4 py-3">Tipo</th>
          <th class="px-4 py-3 text-right">Cantidad</th>
          <th class="px-4 py-3">Razón</th>
          <th class="px-4 py-3">Usuario</th>
          <th class="px-4 py-3 text-right">Acciones</th>
        </tr>
      </thead>

      <tbody class="divide-y divide-gray-800">
        @forelse ($movements as $m)
          @php
            $typeLabel = ucfirst((string) $m->type);
            $typeClass = match((string) $m->type) {
              'entrada' => 'bg-emerald-600/20 text-emerald-300 border border-emerald-700/30',
              'salida'  => 'bg-rose-600/20 text-rose-300 border border-rose-700/30',
              'ajuste'  => 'bg-amber-600/20 text-amber-300 border border-amber-700/30',
              default   => 'bg-gray-700/30 text-gray-200 border border-gray-600/40',
            };

            $productName = optional($m->product)->name ?? '—';
            $productCode = optional($m->product)->code ?? null;
            $userName    = $m->user->name ?? 'Sistema';
            $reason      = $m->reason ?? '—';
            $qty = (int) ($m->quantity ?? 0);
            $createdAt   = optional($m->created_at)->format('Y-m-d H:i') ?? '—';
          @endphp

          <tr class="text-gray-200 hover:bg-gray-900/60 transition">
            {{-- Fecha --}}
            <td class="px-4 py-3 text-gray-300 whitespace-nowrap">
              <div class="font-medium text-gray-200">{{ $createdAt }}</div>
              @if(!empty($m->id))
                <div class="text-[11px] text-gray-500">ID: {{ $m->id }}</div>
              @endif
            </td>

            {{-- Producto --}}
            <td class="px-4 py-3">
              <div class="font-semibold text-gray-100">
                {{ $productName }}
              </div>
              @if($productCode)
                <div class="text-[11px] text-gray-500">SKU: {{ $productCode }}</div>
              @endif
            </td>

            {{-- Tipo --}}
            <td class="px-4 py-3">
              <span class="inline-flex items-center px-2 py-1 rounded text-xs {{ $typeClass }}">
                {{ $typeLabel }}
              </span>
            </td>

            {{-- Cantidad --}}
            <td class="px-4 py-3 text-right text-gray-200 font-semibold tabular-nums whitespace-nowrap">
              {{ number_format($qty, 0, ',', '.') }}
            </td>

            {{-- Razón --}}
            <td class="px-4 py-3 text-gray-300">
              <span title="{{ $reason }}">{{ \Illuminate\Support\Str::limit($reason, 60) }}</span>
            </td>

            {{-- Usuario --}}
            <td class="px-4 py-3 text-gray-300">
              {{ $userName }}
            </td>

            {{-- Acciones --}}
            <td class="px-4 py-3 text-right">
              @if(Route::has('inventory.destroy'))
                <form method="POST" action="{{ route('inventory.destroy', $m) }}"
                      onsubmit="return confirm('¿Eliminar este movimiento? Esta acción no se puede deshacer.');"
                      class="inline">
                  @csrf
                  @method('DELETE')
                  <button type="submit"
                          class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-rose-700/40 text-rose-300 hover:bg-rose-900/30 transition">
                    🗑️ Eliminar
                  </button>
                </form>
              @else
                <span class="text-xs text-gray-500">—</span>
              @endif
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="7" class="px-6 py-10 text-center text-gray-400">
              No hay movimientos para mostrar con los filtros actuales.
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  {{-- Paginación --}}
  <div class="p-4 border-t border-gray-800 bg-gray-950">
    {{ $movements->appends(request()->query())->links() }}
  </div>
</div>

@endsection
