{{-- resources/views/inventory/index.blade.php --}}
@extends('layout.admin')

@section('content')

{{-- Header con título y botones --}}
<div class="flex items-center justify-between mb-6">
  <h1 class="text-2xl font-bold text-emerald-400">📦 Movimientos de Inventario</h1>
  
  <div class="flex gap-2">
    {{-- Botón Volver --}}
    <a href="{{ route('dashboard.index') }}" 
       class="inline-flex items-center gap-2 px-4 py-2 bg-gray-700 text-white rounded-lg shadow hover:bg-gray-600 transition">
      ⬅️ Volver
    </a>

    {{-- Botón Nuevo Movimiento --}}
    <x-create-button route="{{ route('inventory.create') }}" text="Nuevo movimiento" />
  </div>
</div>

{{-- Mensajes flash --}}
<x-flash-message />

{{-- Tabla --}}
<div class="bg-gray-900 rounded-xl shadow-md border border-gray-700">
  <div class="overflow-x-auto rounded-t-xl">
    <table class="min-w-full text-sm text-left">
      <thead class="bg-gray-800 text-gray-300 uppercase text-xs tracking-wide">
        <tr>
          <th class="px-4 py-3">Fecha</th>
          <th class="px-4 py-3">Producto</th>
          <th class="px-4 py-3">Tipo</th>
          <th class="px-4 py-3">Cantidad</th>
          <th class="px-4 py-3">Razón</th>
          <th class="px-4 py-3">Usuario</th>
        </tr>
      </thead>
      <tbody>
@foreach ($movements as $m)
  <tr>
    <td class="px-4 py-3 text-gray-300">
      {{ optional($m->product)->name ?? '—' }}
    </td>

    <td class="px-4 py-3">
      <span class="px-2 py-1 rounded text-xs
        {{ $m->type === 'entrada' ? 'bg-emerald-600/30 text-emerald-300' : 'bg-rose-600/30 text-rose-300' }}">
        {{ ucfirst($m->type) }}
      </span>
    </td>

    <td class="px-4 py-3 text-right text-gray-300">
      {{ number_format($m->qty, 0, ',', '.') }}
    </td>

    <td class="px-4 py-3 text-gray-400">
      {{ $m->reason ?? '—' }}
    </td>

    <td class="px-4 py-3 text-gray-400">
      {{ $m->user->name ?? 'Sistema' }}
    </td>

    <td class="px-4 py-3 text-gray-400">
      {{ optional($m->created_at)->format('Y-m-d H:i') }}
    </td>
  </tr>
@endforeach
</tbody>

    </table>
  </div>
</div>

{{-- Paginación --}}
<div class="p-4 border-t border-gray-700">
  {{ $movements->links() }}
</div>
@endsection
