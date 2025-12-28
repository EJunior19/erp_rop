{{-- resources/views/purchases/index.blade.php --}}
@extends('layout.admin')

@section('content')
<div class="w-full px-6">

  {{-- ================= HEADER ================= --}}
  <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
    <h1 class="text-3xl font-bold text-green-400 flex items-center gap-2">
      🧾 Compras
      <span class="text-sm font-medium text-gray-400">
        ({{ number_format($purchases->total()) }} registros)
      </span>
    </h1>

    <x-create-button route="{{ route('purchases.create') }}" text="Nueva compra" />
  </div>

  <x-flash-message />

  {{-- ================= FILTROS ================= --}}
  <div class="bg-gray-900 text-white rounded-xl shadow-2xl border border-green-400 mb-4">
    <form method="GET" action="{{ route('purchases.index') }}" class="p-4">
      <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">

        {{-- Buscar --}}
        <div class="md:col-span-4">
          <label class="block text-xs uppercase text-gray-400 mb-1">Buscar</label>
          <input name="q" value="{{ request('q') }}"
                 placeholder="Factura, código o proveedor…"
                 class="w-full rounded-lg bg-gray-800 border border-gray-700 px-3 py-2 text-sm focus:border-green-400"/>
        </div>

        {{-- Proveedor --}}
        @isset($suppliers)
          <div class="md:col-span-3">
            <label class="block text-xs uppercase text-gray-400 mb-1">Proveedor</label>
            <select name="supplier_id"
                    class="w-full rounded-lg bg-gray-800 border border-gray-700 px-3 py-2 text-sm focus:border-green-400">
              <option value="">Todos</option>
              @foreach($suppliers as $s)
                <option value="{{ $s->id }}" @selected(request('supplier_id')==$s->id)>
                  {{ $s->name }}
                </option>
              @endforeach
            </select>
          </div>
        @endisset

        {{-- Estado --}}
        <div class="md:col-span-2">
          <label class="block text-xs uppercase text-gray-400 mb-1">Estado</label>
          <select name="status"
                  class="w-full rounded-lg bg-gray-800 border border-gray-700 px-3 py-2 text-sm focus:border-green-400">
            <option value="">Todos</option>
            <option value="pendiente" @selected(request('status')==='pendiente')>Pendiente</option>
            <option value="aprobado"  @selected(request('status')==='aprobado')>Aprobado</option>
            <option value="rechazado" @selected(request('status')==='rechazado')>Rechazado</option>
          </select>
        </div>

        {{-- Fechas --}}
        <div class="md:col-span-1">
          <label class="block text-xs uppercase text-gray-400 mb-1">Desde</label>
          <input type="date" name="from" value="{{ request('from') }}"
                 class="w-full rounded-lg bg-gray-800 border border-gray-700 px-3 py-2 text-sm"/>
        </div>

        <div class="md:col-span-1">
          <label class="block text-xs uppercase text-gray-400 mb-1">Hasta</label>
          <input type="date" name="to" value="{{ request('to') }}"
                 class="w-full rounded-lg bg-gray-800 border border-gray-700 px-3 py-2 text-sm"/>
        </div>

        {{-- Botones --}}
        <div class="md:col-span-1 flex gap-2">
          <button class="px-4 py-2 rounded-lg bg-green-500/20 border border-green-400 text-green-300 hover:bg-green-500/30 text-sm">
            Buscar
          </button>
          <a href="{{ route('purchases.index') }}"
             class="px-4 py-2 rounded-lg bg-gray-800 border border-gray-700 text-gray-300 hover:bg-gray-700 text-sm">
            Limpiar
          </a>
        </div>

      </div>
    </form>
  </div>

  {{-- ================= TABLA ================= --}}
  <div class="bg-gray-900 text-white rounded-xl shadow-2xl border border-green-400 overflow-hidden">

    {{-- SOLO LA TABLA SCROLLEA --}}
    <div class="max-h-[65vh] overflow-y-auto overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-800 text-gray-200 uppercase text-xs sticky top-0 z-10">
          <tr>
            <th class="px-6 py-3 text-right w-16">ID</th>
            <th class="px-6 py-3">Factura</th>
            <th class="px-6 py-3">Proveedor</th>
            <th class="px-6 py-3">Fecha</th>
            <th class="px-6 py-3">Estado</th>
            <th class="px-6 py-3 text-right">Total</th>
            <th class="px-6 py-3 text-right w-40">Acciones</th>
          </tr>
        </thead>

        <tbody class="divide-y divide-gray-700">
          @forelse($purchases as $p)
            <tr class="hover:bg-gray-800/60 transition">
              <td class="px-6 py-3 text-right">{{ $p->id }}</td>

              <td class="px-6 py-3 font-mono">
                {{ $p->invoice_number ?: ($p->code ?? '—') }}
              </td>

              <td class="px-6 py-3">{{ $p->supplier?->name ?? '—' }}</td>

              <td class="px-6 py-3 whitespace-nowrap">
                {{ optional($p->purchased_at)->format('Y-m-d') ?? '—' }}
              </td>

              <td class="px-6 py-3">
                @php
                  $map = [
                    'aprobado'=>'emerald',
                    'pendiente'=>'amber',
                    'rechazado'=>'red'
                  ];
                @endphp
                <x-status-badge :color="$map[$p->status] ?? 'zinc'"
                                :label="ucfirst($p->status ?? '—')" />
              </td>

              <td class="px-6 py-3 text-right font-semibold">
                @money($p->total_amount ?? 0)
              </td>

              <td class="px-6 py-3 text-right">
                <x-action-buttons
                  :show="route('purchases.show',$p)"
                  :edit="route('purchases.edit',$p)"
                  :delete="route('purchases.destroy',$p)"
                  :name="'la compra '.($p->invoice_number ?: '#'.$p->id)" />
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="px-6 py-12 text-center text-gray-400">
                🗂️ No hay compras registradas
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    {{-- PAGINACIÓN FIJA --}}
    <div class="p-4 border-t border-gray-700 bg-gray-900">
      {{ $purchases->appends(request()->query())->links() }}
    </div>

  </div>
</div>
@endsection
