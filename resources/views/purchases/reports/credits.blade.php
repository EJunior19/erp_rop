@extends('layout.admin')

@section('content')
@php
    use Illuminate\Support\Str;
    use Illuminate\Pagination\AbstractPaginator;
    use Illuminate\Support\Carbon;

    // --- Fallback: calcula $pendingTotal si el controller no lo envió ---
    if (!isset($pendingTotal)) {
        $collection = $credits instanceof AbstractPaginator ? $credits->getCollection() : collect($credits);
        $pendingTotal = $collection
            ->filter(fn ($c) => in_array(Str::lower($c->status ?? ''), ['pending','pendiente']))
            ->sum('amount');
    }
@endphp

<div class="mb-6 flex items-center justify-between">
  <h1 class="text-2xl font-bold text-amber-400">💳 Reporte de Cuentas por Cobrar</h1>

  {{-- Botones de exportación --}}
  <div class="flex gap-2">
    <a href="{{ route('reports.credits.pdf', request()->all()) }}" target="_blank"
       class="px-4 py-2 rounded-lg bg-red-600 text-white hover:bg-red-500 transition">
      📄 Exportar PDF
    </a>
    <a href="{{ route('reports.credits.print', request()->all()) }}" target="_blank"
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
          class="px-4 py-2 rounded-lg bg-amber-600 text-white hover:bg-amber-500 transition">
    Filtrar
  </button>
</form>

<div class="rounded-xl border border-zinc-800 bg-zinc-900 overflow-hidden shadow">
  <table class="min-w-full text-sm">
    <thead class="bg-zinc-800 text-zinc-300 uppercase text-xs tracking-wider">
      <tr>
        <th class="px-4 py-3 text-left">#</th>
        <th class="px-4 py-3 text-left">Cliente</th>
        <th class="px-4 py-3 text-left">Monto</th>
        <th class="px-4 py-3 text-left">Vencimiento</th>
        <th class="px-4 py-3 text-left">Estado</th>
      </tr>
    </thead>

    <tbody class="divide-y divide-zinc-800 text-zinc-200">
      @forelse($credits as $c)
        <tr class="hover:bg-zinc-800/50 transition">
          <td class="px-4 py-3 font-mono">#{{ $c->id }}</td>

          <td class="px-4 py-3">{{ $c->client->name ?? '—' }}</td>

          <td class="px-4 py-3 font-semibold text-amber-400">
            Gs. {{ number_format((float) ($c->amount ?? 0), 0, ',', '.') }}
          </td>

          <td class="px-4 py-3 text-zinc-300">
            @php
              $date = $c->due_date ?? null;
              // si viene string => parse; si viene Carbon => usar; si es null => '—'
              $due = $date instanceof \Carbon\Carbon ? $date : (filled($date) ? Carbon::parse($date) : null);
            @endphp
            {{ $due?->format('Y-m-d') ?? '—' }}
          </td>

          <td class="px-4 py-3">
            @php
              $raw = (string) ($c->status ?? '');
              $status = trim(Str::lower($raw));
              $map = [
                'pagado'    => ['emerald',  'Pagado'],
                'paid'      => ['emerald',  'Pagado'],
                'pendiente' => ['amber',    'Pendiente'],
                'pending'   => ['amber',    'Pendiente'],
                'vencido'   => ['red',      'Vencido'],
                'overdue'   => ['red',      'Vencido'],
              ];
              [$color, $label] = $map[$status] ?? ['zinc', ($raw !== '' ? $raw : '—')];
            @endphp

            {{-- Debug rápido: mostrará el valor crudo si algo falla --}}
            {{-- <span class="text-xs text-zinc-400">({{ $raw === '' ? 'vacío' : $raw }})</span> --}}

            <x-status-badge :color="$color" :label="$label" />
          </td>


        </tr>
      @empty
        <tr>
          <td colspan="5" class="px-4 py-6 text-center text-zinc-500 italic">
            🚫 No hay créditos registrados
          </td>
        </tr>
      @endforelse
    </tbody>
  </table>

  <div class="p-4 text-right font-semibold text-amber-400 border-t border-zinc-800">
    Total pendiente: Gs. {{ number_format((float) $pendingTotal, 0, ',', '.') }}
  </div>
</div>

{{-- Paginación (si corresponde) --}}
@if($credits instanceof \Illuminate\Pagination\AbstractPaginator)
  <div class="mt-4">
    {{ $credits->withQueryString()->links() }}
  </div>
@endif
@endsection
