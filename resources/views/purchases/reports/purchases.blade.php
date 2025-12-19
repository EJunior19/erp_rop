{{-- resources/views/reports/purchases.blade.php --}}
@extends('layout.admin')

@section('title','Reporte · Compras')

@section('content')
<div class="w-full">
  {{-- Header --}}
  <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
    <div>
      <h1 class="text-3xl font-bold text-sky-400 flex items-center gap-2">
        🛒 Reporte de Compras
        @isset($purchases)
          <span class="text-sm font-medium text-gray-400">
            ({{ method_exists($purchases,'total') ? number_format($purchases->total()) : number_format($purchases->count()) }} registros)
          </span>
        @endisset
      </h1>
      <p class="text-sm text-gray-500 mt-1">Filtrá por fechas y exportá el reporte.</p>
    </div>

    {{-- Export --}}
    <div class="flex flex-wrap items-center gap-2">
      <a href="{{ route('reports.purchases.pdf', request()->all()) }}" target="_blank"
         class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-red-600 text-white hover:bg-red-500 transition shadow">
        📄 Exportar PDF
      </a>
      <a href="{{ route('reports.purchases.print', request()->all()) }}" target="_blank"
         class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-500 transition shadow">
        🖨️ Imprimir
      </a>
    </div>
  </div>

  {{-- Filtros --}}
  <div class="bg-gray-900 text-white rounded-xl shadow-2xl border-2 border-sky-400 mb-4">
    <form method="GET" class="p-4">
      <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
        <div class="md:col-span-3">
          <label class="block text-xs uppercase tracking-wide text-gray-400 mb-1">Desde</label>
          <input type="date" name="from" value="{{ request('from') }}"
                 class="w-full rounded-lg bg-gray-800 border border-gray-700 focus:border-sky-400 focus:ring-0 px-3 py-2 text-sm">
        </div>

        <div class="md:col-span-3">
          <label class="block text-xs uppercase tracking-wide text-gray-400 mb-1">Hasta</label>
          <input type="date" name="to" value="{{ request('to') }}"
                 class="w-full rounded-lg bg-gray-800 border border-gray-700 focus:border-sky-400 focus:ring-0 px-3 py-2 text-sm">
        </div>

        <div class="md:col-span-3 flex gap-2">
          <button type="submit"
                  class="w-full inline-flex items-center justify-center px-4 py-2 rounded-lg bg-sky-500/20 border border-sky-400 text-sky-300 hover:bg-sky-500/30 text-sm font-medium transition">
            Buscar
          </button>
          <a href="{{ route('reports.purchases') }}"
             class="w-full inline-flex items-center justify-center px-4 py-2 rounded-lg bg-gray-800 border border-gray-700 text-gray-300 hover:bg-gray-700 text-sm font-medium transition">
            Limpiar
          </a>
        </div>

        {{-- Totales del filtro (opcional, visual) --}}
        <div class="md:col-span-3 text-sm text-gray-300 md:text-right">
          @php
            // Si es paginator, para sumar total del filtro, lo ideal es que el controller envíe $grandTotal
            // Este fallback suma lo que está en la página actual.
            $pageTotal = 0;
            if (isset($purchases)) {
              $col = method_exists($purchases,'getCollection') ? $purchases->getCollection() : collect($purchases);
              $pageTotal = $col->sum(fn($x) => (float) ($x->total_amount ?? $x->total ?? 0));
            }
          @endphp
          <div class="text-xs text-gray-500 uppercase tracking-wide">Total (página)</div>
          <div class="font-semibold text-sky-300 tabular-nums">Gs. {{ number_format($pageTotal, 0, ',', '.') }}</div>
        </div>
      </div>
    </form>
  </div>

  {{-- Tabla --}}
  <div class="bg-gray-900 text-white rounded-xl shadow-2xl border-2 border-sky-400">
    <div class="overflow-x-auto max-h-[70vh] rounded-t-xl">
      <table class="min-w-full text-sm text-left">
        <thead class="bg-gray-800 text-gray-200 uppercase text-xs tracking-wide sticky top-0 z-10">
          <tr>
            <th class="px-6 py-3">Factura</th>
            <th class="px-6 py-3">Proveedor</th>
            <th class="px-6 py-3">Fecha</th>
            <th class="px-6 py-3 text-right">Total</th>
          </tr>
        </thead>

        <tbody class="divide-y divide-gray-700">
          @forelse($purchases as $p)
            @php
              $invoice = $p->invoice_number ?? $p->code ?? '—';
              $supplierName = $p->supplier?->name ?? '—';
              $date = optional($p->purchased_at)->format('Y-m-d') ?? optional($p->created_at)->format('Y-m-d') ?? '—';
              $total = (float) ($p->total_amount ?? $p->total ?? 0);
            @endphp

            <tr class="hover:bg-gray-800/60 transition">
              <td class="px-6 py-3">
                <span class="font-mono text-gray-200 whitespace-nowrap">{{ $invoice }}</span>
              </td>

              <td class="px-6 py-3">
                <span class="text-gray-200">{{ $supplierName }}</span>
              </td>

              <td class="px-6 py-3 whitespace-nowrap text-gray-300">
                {{ $date }}
              </td>

              <td class="px-6 py-3 text-right font-semibold tabular-nums text-sky-300">
                Gs. {{ number_format($total, 0, ',', '.') }}
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="4" class="px-6 py-12">
                <div class="flex flex-col items-center justify-center text-center text-gray-400">
                  <div class="text-5xl mb-3">🗂️</div>
                  <p class="font-semibold">Sin compras</p>
                  <p class="text-sm">No se encontraron registros para el filtro aplicado.</p>
                </div>
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    {{-- Footer / Totales + paginación --}}
    <div class="p-4 border-t border-gray-700 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
      @php
        $totalAll = null;
        if (isset($grandTotal)) $totalAll = (float) $grandTotal; // si lo mandás desde controller (recomendado)
      @endphp

      <div class="text-sm text-gray-300">
        @if($totalAll !== null)
          <span class="text-gray-500">Total (filtrado):</span>
          <span class="font-semibold text-sky-300 tabular-nums">Gs. {{ number_format($totalAll, 0, ',', '.') }}</span>
        @else
          <span class="text-gray-500">Tip:</span>
          <span class="text-gray-300">si querés el total real del filtro (no solo la página), enviá <span class="font-mono text-gray-200">$grandTotal</span> desde el controller.</span>
        @endif
      </div>

      @if(method_exists($purchases,'withQueryString'))
        <div>
          {{ $purchases->withQueryString()->links() }}
        </div>
      @endif
    </div>
  </div>
</div>
@endsection
