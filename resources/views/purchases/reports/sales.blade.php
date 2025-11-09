@extends('layout.admin')

@section('content')
@php
  // Métricas del conjunto actualmente mostrado (según filtros aplicados)
  $countAll   = $sales instanceof \Illuminate\Pagination\LengthAwarePaginator ? $sales->total() : $sales->count();
  $totalGs    = (int) ($sales->sum('total') ?? 0);
  $avgTicket  = $countAll ? intdiv($totalGs, $countAll) : 0;

  $aprobadas  = $sales->where('estado','aprobado')->count();
  $rechazadas = $sales->where('estado','rechazado')->count();
  $pendientes = $sales->where('estado','pendiente')->count();
@endphp

<div class="mb-6 flex items-center justify-between gap-3">
  <h1 class="text-2xl font-bold text-emerald-400">📊 Reporte de Ventas</h1>

  {{-- Botones de exportación --}}
  <div class="flex gap-2">
    <a href="{{ route('reports.sales.pdf', request()->all()) }}" target="_blank"
       class="px-4 py-2 rounded-lg bg-red-600 text-white hover:bg-red-500 transition">
      📄 Exportar PDF
    </a>
    <a href="{{ route('reports.sales.print', request()->all()) }}" target="_blank"
       class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-500 transition">
      🖨️ Imprimir
    </a>
  </div>
</div>

{{-- 🔍 Filtros de fechas --}}
<form method="GET" class="flex flex-wrap items-end gap-3 mb-6">
  <div class="flex flex-col">
    <label class="text-xs text-zinc-400 mb-1">Desde</label>
    <input type="date" name="from" value="{{ request('from') }}"
           class="rounded-lg bg-zinc-900 border border-zinc-700 text-zinc-100 px-3 py-2">
  </div>

  <div class="flex flex-col">
    <label class="text-xs text-zinc-400 mb-1">Hasta</label>
    <input type="date" name="to" value="{{ request('to') }}"
           class="rounded-lg bg-zinc-900 border border-zinc-700 text-zinc-100 px-3 py-2">
  </div>

  <button type="submit"
          class="px-4 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-500 transition">
    Filtrar
  </button>

  @if(request()->hasAny(['from','to']))
    <a href="{{ route('reports.sales') }}"
       class="px-4 py-2 rounded-lg border border-zinc-700 text-zinc-200 hover:bg-zinc-800 transition">
      Limpiar
    </a>
  @endif
</form>

{{-- KPI rápidos del rango --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
  <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
    <p class="text-xs text-zinc-400">Ventas</p>
    <p class="text-xl font-semibold text-zinc-100">{{ number_format($countAll, 0, ',', '.') }}</p>
  </div>
  <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
    <p class="text-xs text-zinc-400">Total (Gs.)</p>
    <p class="text-xl font-semibold text-emerald-400">Gs. {{ number_format($totalGs, 0, ',', '.') }}</p>
  </div>
  <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
    <p class="text-xs text-zinc-400">Ticket promedio</p>
    <p class="text-xl font-semibold text-zinc-100">Gs. {{ number_format($avgTicket, 0, ',', '.') }}</p>
  </div>
  <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
    <p class="text-xs text-zinc-400">Estados</p>
    <p class="text-sm text-zinc-300">
      ✅ {{ $aprobadas }} · 🟡 {{ $pendientes }} · ❌ {{ $rechazadas }}
    </p>
  </div>
</div>

{{-- Tabla --}}
<div class="rounded-xl border border-zinc-800 bg-zinc-900 overflow-hidden shadow">
  <div class="max-h-[65vh] overflow-auto">
    <table class="min-w-full text-sm">
      <thead class="bg-zinc-800 text-zinc-300 uppercase text-xs tracking-wider sticky top-0 z-10">
        <tr>
          <th class="px-4 py-3 text-left">#</th>
          <th class="px-4 py-3 text-left">Cliente</th>
          <th class="px-4 py-3 text-left">Modo</th>
          <th class="px-4 py-3 text-left">Estado</th>
          <th class="px-4 py-3 text-right">Total</th>
          <th class="px-4 py-3 text-left">Fecha</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-zinc-800 text-zinc-200">
        @forelse($sales as $s)
          <tr class="hover:bg-zinc-800/50 transition">
            <td class="px-4 py-3 font-mono">#{{ $s->id }}</td>
            <td class="px-4 py-3">
              {{ $s->client->name ?? '—' }}
            </td>
            <td class="px-4 py-3">
              {{ $s->modo_pago ? ucfirst($s->modo_pago) : '—' }}
            </td>
            <td class="px-4 py-3">
              <x-status-badge
                :color="$s->status === 'aprobado' ? 'emerald' : ($s->status === 'rechazado' ? 'red' : 'amber')"
                :label="ucfirst($s->estado ?? 'pendiente')" />
            </td>
            <td class="px-4 py-3 font-semibold text-emerald-400 text-right">
              Gs. {{ number_format((int)($s->total ?? 0), 0, ',', '.') }}
            </td>
            <td class="px-4 py-3 text-zinc-400">
              {{ optional($s->fecha)->format('Y-m-d') ?? optional($s->created_at)->format('Y-m-d') }}
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="6" class="px-4 py-6 text-center text-zinc-500 italic">
              🚫 No se encontraron ventas en el rango seleccionado
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  {{-- Totales del bloque listado (no de toda la BD) --}}
  <div class="p-4 flex flex-wrap items-center justify-between gap-3 border-t border-zinc-800">
    <div class="text-zinc-300">
      Mostrando:
      <span class="font-semibold">{{ $sales instanceof \Illuminate\Pagination\LengthAwarePaginator ? $sales->count() : $sales->count() }}</span>
      de
      <span class="font-semibold">{{ $sales instanceof \Illuminate\Pagination\LengthAwarePaginator ? $sales->total() : $sales->count() }}</span>
      registros
    </div>

    <div class="text-right font-semibold text-emerald-400">
      Total página: Gs. {{ number_format((int)$sales->sum('total'), 0, ',', '.') }}
    </div>
  </div>

  {{-- Paginación (si corresponde) --}}
  @if ($sales instanceof \Illuminate\Pagination\LengthAwarePaginator)
    <div class="p-4 border-t border-zinc-800">
      {{ $sales->appends(request()->all())->links() }}
    </div>
  @endif
</div>
@endsection
