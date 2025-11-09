@extends('layout.admin')

@section('content')
<div class="mb-6 flex items-center justify-between">
  <h1 class="text-2xl font-bold text-purple-400">📦 Reporte de Inventario</h1>

  {{-- Botones de exportación --}}
  <div class="flex gap-2">
    <a href="{{ route('reports.inventory.pdf', request()->all()) }}" target="_blank"
       class="px-4 py-2 rounded-lg bg-red-600 text-white hover:bg-red-500 transition">
      📄 Exportar PDF
    </a>
    <a href="{{ route('reports.inventory.print', request()->all()) }}" target="_blank"
       class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-500 transition">
      🖨️ Imprimir
    </a>
  </div>
</div>

<form method="GET" class="flex gap-3 mb-6">
  <input type="date" name="from" value="{{ request('from') }}"
         class="rounded-lg bg-zinc-900 border border-zinc-700 text-zinc-100 px-3 py-2">
  <input type="date" name="to" value="{{ request('to') }}"
         class="rounded-lg bg-zinc-900 border border-zinc-700 text-zinc-100 px-3 py-2">

  <button type="submit"
          class="px-4 py-2 rounded-lg bg-purple-600 text-white hover:bg-purple-500 transition">
    Filtrar
  </button>
</form>

<div class="rounded-xl border border-zinc-800 bg-zinc-900 overflow-hidden shadow">
  <table class="min-w-full text-sm text-center">
    <thead class="bg-zinc-800 text-zinc-300 uppercase text-xs tracking-wider">
      <tr>
        <th class="px-4 py-3">Factura</th>
        <th class="px-4 py-3">Producto</th>
        <th class="px-4 py-3">Tipo</th>
        <th class="px-4 py-3">Cantidad</th>
        <th class="px-4 py-3">Fecha</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-zinc-800 text-zinc-200">
      @forelse($movements as $m)
        <tr class="hover:bg-zinc-800/50 transition">
          {{-- Número de factura (reason guarda: Compra #ID o Venta #ID) --}}
          <td class="px-4 py-3 font-mono">{{ $m->reason ?? '—' }}</td>
          <td class="px-4 py-3">{{ $m->product->name ?? '—' }}</td>
          <td class="px-4 py-3">{{ ucfirst($m->type) }}</td>
          <td class="px-4 py-3 font-semibold text-purple-400">{{ $m->quantity }}</td>
          <td class="px-4 py-3 text-zinc-400">
            {{ $m->created_at->format('Y-m-d') }}
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="5" class="px-4 py-6 text-center text-zinc-500 italic">
            🚫 No hay movimientos de inventario
          </td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
