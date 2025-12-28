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

    <div class="md:col-span-2">
      <label class="block text-xs font-semibold uppercase text-gray-400 mb-1">Buscar</label>
      <input type="text" name="q" value="{{ $q }}"
             placeholder="Producto, razón, usuario…"
             class="w-full rounded-lg bg-gray-900 border border-gray-700 text-white px-3 py-2 focus:ring-emerald-600/40">
    </div>

    <div>
      <label class="block text-xs font-semibold uppercase text-gray-400 mb-1">Tipo</label>
      <select name="type"
              class="w-full rounded-lg bg-gray-900 border border-gray-700 text-white px-3 py-2">
        <option value="">Todos</option>
        <option value="entrada" @selected($type==='entrada')>Entrada</option>
        <option value="salida"  @selected($type==='salida')>Salida</option>
        <option value="ajuste"  @selected($type==='ajuste')>Ajuste</option>
      </select>
    </div>

    <div>
      <label class="block text-xs font-semibold uppercase text-gray-400 mb-1">Desde</label>
      <input type="date" name="from" value="{{ $from }}"
             class="w-full rounded-lg bg-gray-900 border border-gray-700 text-white px-3 py-2">
    </div>

    <div>
      <label class="block text-xs font-semibold uppercase text-gray-400 mb-1">Hasta</label>
      <input type="date" name="to" value="{{ $to }}"
             class="w-full rounded-lg bg-gray-900 border border-gray-700 text-white px-3 py-2">
    </div>

  </div>

  <div class="flex flex-wrap gap-2 mt-4">
    <button class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-semibold">
      🔎 Buscar
    </button>

    <a href="{{ route('inventory.index') }}"
       class="px-4 py-2 rounded-lg bg-gray-800 hover:bg-gray-700 border border-gray-700 text-gray-200">
      ✖ Limpiar
    </a>

    <a href="{{ route('inventory.index', array_merge(request()->query(), ['export'=>'csv'])) }}"
       class="px-4 py-2 rounded-lg bg-sky-600 hover:bg-sky-700 text-white font-semibold">
      📤 Exportar CSV
    </a>

    <div class="ml-auto text-xs text-gray-400 flex items-center gap-2">
      <span class="px-2 py-1 rounded bg-gray-900 border border-gray-800">
        Registros: <b class="text-gray-200">{{ $movements->total() }}</b>
      </span>
    </div>
  </div>
</form>

{{-- ================= TABLA (SOLO ESTO SCROLLEA) ================= --}}
<div class="bg-gray-950 rounded-xl shadow-lg border border-gray-800/60 overflow-hidden">

  <div class="max-h-[65vh] overflow-y-auto">
    <table class="min-w-full text-sm text-left">
      <thead class="bg-gray-900 text-gray-300 uppercase text-xs tracking-wide sticky top-0 z-20">
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
            $productName = optional($m->product)->name ?? '—';
          @endphp

          <tr class="hover:bg-gray-900/60 transition text-gray-200">
            <td class="px-4 py-3">{{ $m->created_at?->format('Y-m-d H:i') }}</td>
            <td class="px-4 py-3 font-semibold">{{ $productName }}</td>
            <td class="px-4 py-3">{{ ucfirst($m->type) }}</td>
            <td class="px-4 py-3 text-right font-bold">{{ (int)$m->qty }}</td>
            <td class="px-4 py-3 text-gray-400">{{ $m->reason ?? '—' }}</td>
            <td class="px-4 py-3">{{ $m->user->name ?? 'Sistema' }}</td>
            <td class="px-4 py-3 text-right">
              <x-delete-button 
                :action="route('inventory.destroy', $m)"
                :name="'el movimiento del producto '.$productName" />
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="7" class="px-6 py-10 text-center text-gray-400">
              No hay movimientos para mostrar.
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="p-4 border-t border-gray-800 bg-gray-950">
    {{ $movements->appends(request()->query())->links() }}
  </div>
</div>

@endsection
