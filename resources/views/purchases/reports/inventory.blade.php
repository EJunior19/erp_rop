@extends('layout.admin')

@section('content')
<div class="max-w-full overflow-x-hidden">

  <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
    <h1 class="text-2xl font-bold text-purple-400">📦 Reporte de Inventario</h1>

    <div class="flex flex-wrap gap-2">
      <a href="{{ route('reports.inventory.pdf', request()->all()) }}" target="_blank"
         class="px-4 py-2 rounded-lg bg-red-600 text-white hover:bg-red-500 transition">
        📄 Exportar PDF
      </a>
      <a href="{{ route('reports.inventory.print', request()->all()) }}" target="_blank"
         class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-500 transition">
        🖨️ Imprimir
      </a>
      <a href="{{ route('dashboard.index') }}"
         class="px-4 py-2 rounded-lg bg-gray-800 border border-gray-700 text-gray-200 hover:bg-gray-700 transition">
        ⬅️ Volver
      </a>
    </div>
  </div>

  <form method="GET" class="flex flex-col md:flex-row gap-3 mb-6">
    <input type="date" name="from" value="{{ request('from') }}"
           class="rounded-lg bg-zinc-900 border border-zinc-700 text-zinc-100 px-3 py-2">
    <input type="date" name="to" value="{{ request('to') }}"
           class="rounded-lg bg-zinc-900 border border-zinc-700 text-zinc-100 px-3 py-2">

    <div class="flex gap-2">
      <button type="submit"
              class="px-4 py-2 rounded-lg bg-purple-600 text-white hover:bg-purple-500 transition">
        Filtrar
      </button>
      <a href="{{ route('reports.inventory') }}"
         class="px-4 py-2 rounded-lg bg-gray-800 border border-gray-700 text-gray-200 hover:bg-gray-700 transition">
        Limpiar
      </a>
    </div>
  </form>

  <div class="rounded-xl border border-zinc-800 bg-zinc-900 overflow-hidden shadow">
    <div class="max-w-full overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-zinc-800 text-zinc-300 uppercase text-xs tracking-wider">
          <tr>
            <th class="px-4 py-3 text-left whitespace-nowrap">Factura / Razón</th>
            <th class="px-4 py-3 text-left">Producto</th>
            <th class="px-4 py-3 text-center whitespace-nowrap">Tipo</th>
            <th class="px-4 py-3 text-right whitespace-nowrap">Cantidad</th>
            <th class="px-4 py-3 text-center whitespace-nowrap">Fecha</th>
          </tr>
        </thead>

        <tbody class="divide-y divide-zinc-800 text-zinc-200">
          @forelse($movements as $m)
            @php
              $qty = $m->qty ?? $m->quantity ?? 0; // ✅ fallback
              $type = strtolower((string)($m->type ?? ''));
              $badge = $type === 'entrada'
                ? 'bg-emerald-600/20 text-emerald-300 border-emerald-500/40'
                : 'bg-rose-600/20 text-rose-300 border-rose-500/40';
            @endphp

            <tr class="hover:bg-zinc-800/50 transition">
              {{-- reason guarda: "Compra #ID" o "Venta #ID" --}}
              <td class="px-4 py-3 font-mono text-zinc-300 whitespace-nowrap">
                {{ $m->reason ?? '—' }}
              </td>

              <td class="px-4 py-3">
                <div class="font-semibold text-zinc-100">
                  {{ $m->product->name ?? '—' }}
                </div>
                @if(!empty($m->product?->code))
                  <div class="text-[11px] text-zinc-400 font-mono">SKU: {{ $m->product->code }}</div>
                @endif
              </td>

              <td class="px-4 py-3 text-center">
                <span class="inline-flex items-center px-2 py-1 rounded-lg text-xs border {{ $badge }}">
                  {{ $type ? ucfirst($type) : '—' }}
                </span>
              </td>

              <td class="px-4 py-3 text-right font-bold text-purple-300 tabular-nums whitespace-nowrap">
                {{ number_format((float)$qty, 0, ',', '.') }}
              </td>

              <td class="px-4 py-3 text-center text-zinc-400 whitespace-nowrap">
                {{ optional($m->created_at)->format('Y-m-d H:i') ?? '—' }}
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="5" class="px-6 py-10 text-center text-zinc-500 italic">
                🚫 No hay movimientos de inventario
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    {{-- Si viene paginado --}}
    @if(method_exists($movements, 'links'))
      <div class="p-4 border-t border-zinc-800">
        {{ $movements->withQueryString()->links() }}
      </div>
    @endif
  </div>

</div>
@endsection
