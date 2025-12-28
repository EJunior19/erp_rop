@extends('layout.admin')

@section('title','Reporte · Compras')

@section('content')
@php
  $countAll  = $purchases instanceof \Illuminate\Pagination\LengthAwarePaginator
                ? $purchases->total()
                : $purchases->count();

  $pageTotal = $purchases->sum(fn($p) => (float)($p->total_amount ?? $p->total ?? 0));
  $avgTicket = $countAll ? intdiv((int)$pageTotal, max($purchases->count(),1)) : 0;
@endphp

{{-- ================= HEADER ================= --}}
<div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-6">
  <div>
    <h1 class="text-2xl font-bold text-purple-400 flex items-center gap-2">
      🛒 Reporte de Compras
    </h1>
    <p class="text-sm text-zinc-400 mt-1">
      Resumen de compras registradas por período.
    </p>
  </div>

  <div class="flex flex-wrap gap-2">
    <a href="{{ route('reports.purchases.pdf', request()->all()) }}" target="_blank"
       class="px-4 py-2 rounded-lg bg-red-600 text-white hover:bg-red-500 transition">
      📄 Exportar PDF
    </a>
    <a href="{{ route('reports.purchases.print', request()->all()) }}" target="_blank"
       class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-500 transition">
      🖨️ Imprimir
    </a>
    <a href="{{ route('dashboard.index') }}"
       class="px-4 py-2 rounded-lg bg-zinc-800 border border-zinc-700 text-zinc-200 hover:bg-zinc-700 transition">
      ⬅️ Volver
    </a>
  </div>
</div>

{{-- ================= FILTROS ================= --}}
<form method="GET" class="bg-zinc-950 border border-zinc-800 rounded-xl p-4 mb-6">
  <div class="flex flex-wrap items-end gap-3">
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
            class="px-4 py-2 rounded-lg bg-purple-600 text-white hover:bg-purple-500 transition">
      Filtrar
    </button>

    @if(request()->hasAny(['from','to']))
      <a href="{{ route('reports.purchases') }}"
         class="px-4 py-2 rounded-lg bg-zinc-800 border border-zinc-700 text-zinc-200 hover:bg-zinc-700 transition">
        Limpiar
      </a>
    @endif
  </div>
</form>

{{-- ================= KPI ================= --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
  <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
    <p class="text-xs text-zinc-400">Compras</p>
    <p class="text-xl font-semibold text-zinc-100">
      {{ number_format($countAll, 0, ',', '.') }}
    </p>
  </div>

  <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
    <p class="text-xs text-zinc-400">Total (página)</p>
    <p class="text-xl font-semibold text-purple-400">
      Gs. {{ number_format((int)$pageTotal, 0, ',', '.') }}
    </p>
  </div>

  <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
    <p class="text-xs text-zinc-400">Ticket promedio</p>
    <p class="text-xl font-semibold text-zinc-100">
      Gs. {{ number_format((int)$avgTicket, 0, ',', '.') }}
    </p>
  </div>

  <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4">
    <p class="text-xs text-zinc-400">Registros visibles</p>
    <p class="text-sm text-zinc-300">
      {{ $purchases->count() }} en esta página
    </p>
  </div>
</div>

{{-- ================= TABLA ================= --}}
<div class="rounded-xl border border-zinc-800 bg-zinc-900 shadow overflow-hidden">

  {{-- SOLO LA TABLA SCROLLEA --}}
  <div class="max-h-[65vh] overflow-y-auto overflow-x-auto">
    <table class="min-w-full text-sm">

      <thead class="bg-zinc-800 text-zinc-300 uppercase text-xs tracking-wider sticky top-0 z-20">
        <tr>
          <th class="px-4 py-3 text-left">Factura</th>
          <th class="px-4 py-3 text-left">Proveedor</th>
          <th class="px-4 py-3 text-left">Fecha</th>
          <th class="px-4 py-3 text-right">Total</th>
        </tr>
      </thead>

      <tbody class="divide-y divide-zinc-800 text-zinc-200">
        @forelse($purchases as $p)
          @php
            $invoice  = $p->invoice_number ?? $p->code ?? '—';
            $supplier = $p->supplier?->name ?? '—';
            $date     = optional($p->purchased_at)->format('Y-m-d')
                        ?? optional($p->created_at)->format('Y-m-d')
                        ?? '—';
            $total    = (float) ($p->total_amount ?? $p->total ?? 0);
          @endphp

          <tr class="hover:bg-zinc-800/50 transition">
            <td class="px-4 py-3 font-mono whitespace-nowrap">
              {{ $invoice }}
            </td>

            <td class="px-4 py-3">
              {{ $supplier }}
            </td>

            <td class="px-4 py-3 text-zinc-400 whitespace-nowrap">
              {{ $date }}
            </td>

            <td class="px-4 py-3 text-right font-semibold text-purple-400 tabular-nums">
              Gs. {{ number_format($total, 0, ',', '.') }}
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="4" class="px-4 py-8 text-center text-zinc-500 italic">
              🚫 No se encontraron compras en el rango seleccionado
            </td>
          </tr>
        @endforelse
      </tbody>

    </table>
  </div>

  {{-- FOOTER FIJO --}}
  <div class="p-4 flex flex-wrap items-center justify-between gap-3 border-t border-zinc-800">
    <div class="text-zinc-300">
      Mostrando
      <span class="font-semibold">{{ $purchases->count() }}</span>
      de
      <span class="font-semibold">
        {{ $purchases instanceof \Illuminate\Pagination\LengthAwarePaginator ? $purchases->total() : $purchases->count() }}
      </span>
      registros
    </div>

    <div class="font-semibold text-purple-400">
      Total página: Gs. {{ number_format((int)$pageTotal, 0, ',', '.') }}
    </div>
  </div>

  {{-- PAGINACIÓN --}}
  @if ($purchases instanceof \Illuminate\Pagination\LengthAwarePaginator)
    <div class="p-4 border-t border-zinc-800">
      {{ $purchases->appends(request()->all())->links() }}
    </div>
  @endif

</div>
@endsection
