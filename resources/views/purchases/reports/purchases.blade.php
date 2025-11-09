@extends('layout.admin')

@section('content')
<div class="mb-6 flex items-center justify-between">
  <h1 class="text-2xl font-bold text-sky-400">🛒 Reporte de Compras</h1>

  {{-- Botones de exportación --}}
  <div class="flex gap-2">
    <a href="{{ route('reports.purchases.pdf', request()->all()) }}" target="_blank"
       class="px-4 py-2 rounded-lg bg-red-600 text-white hover:bg-red-500 transition">
      📄 Exportar PDF
    </a>
    <a href="{{ route('reports.purchases.print', request()->all()) }}" target="_blank"
       class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-500 transition">
      🖨️ Imprimir
    </a>
  </div>
</div>

{{-- Filtro --}}
<form method="GET" class="flex gap-3 mb-6">
  <input type="date" name="from" value="{{ request('from') }}"
         class="rounded-lg bg-zinc-900 border border-zinc-700 text-zinc-100 px-3 py-2">
  <input type="date" name="to" value="{{ request('to') }}"
         class="rounded-lg bg-zinc-900 border border-zinc-700 text-zinc-100 px-3 py-2">

  <button type="submit"
          class="px-4 py-2 rounded-lg bg-sky-600 text-white hover:bg-sky-500 transition">
    Filtrar
  </button>
</form>

<div class="rounded-xl border border-zinc-800 bg-zinc-900 overflow-hidden shadow">
  <table class="min-w-full text-sm text-center">
    <thead class="bg-zinc-800 text-zinc-300 uppercase text-xs tracking-wider">
      <tr>
        <th class="px-4 py-3">Factura</th>
        <th class="px-4 py-3">Proveedor</th>
        <th class="px-4 py-3">Total</th>
        <th class="px-4 py-3">Fecha</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-zinc-800 text-zinc-200">
      @forelse($purchases as $p)
        <tr class="hover:bg-zinc-800/50 transition">
          {{-- Número de Factura en lugar de # --}}
          <td class="px-4 py-3 font-mono">{{ $p->invoice_number ?? '—' }}</td>
          <td class="px-4 py-3">{{ $p->supplier->name ?? '—' }}</td>
          <td class="px-4 py-3 font-semibold text-sky-400">
            Gs. {{ number_format($p->total ?? 0, 0, ',', '.') }}
          </td>
          <td class="px-4 py-3 text-zinc-400">
            {{ optional($p->purchased_at)->format('Y-m-d') ?? $p->created_at->format('Y-m-d') }}
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="4" class="px-4 py-6 text-center text-zinc-500 italic">
            🚫 No se encontraron compras
          </td>
        </tr>
      @endforelse
    </tbody>
  </table>

  <div class="p-4 text-right font-semibold text-sky-400 border-t border-zinc-800">
    Total: Gs. {{ number_format($purchases->sum('total'), 0, ',', '.') }}
  </div>
</div>
@endsection
