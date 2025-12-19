{{-- resources/views/reports/credits.blade.php --}}
@extends('layout.admin')

@section('content')
@php
  use Illuminate\Support\Str;
  use Illuminate\Pagination\AbstractPaginator;
  use Illuminate\Support\Carbon;

  $collection = $credits instanceof AbstractPaginator ? $credits->getCollection() : collect($credits);

  if (!isset($pendingTotal)) {
      $pendingTotal = $collection
          ->filter(function ($c) {
              $st = Str::lower((string)($c->status ?? ''));
              return in_array($st, ['pending','pendiente'], true);
          })
          ->sum(fn ($c) => (float)($c->amount ?? 0));
  }

  $totalListado = $collection->sum(fn($c) => (float)($c->amount ?? 0));
@endphp

<div class="max-w-full overflow-x-hidden">

  {{-- Header --}}
  <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-6">
    <h1 class="text-3xl font-bold text-amber-400 flex items-center gap-2">
      💳 Reporte de Cuentas por Cobrar
      <span class="text-sm font-medium text-gray-400">
        ({{ number_format($credits instanceof AbstractPaginator ? $credits->total() : $collection->count()) }} registros)
      </span>
    </h1>

    <div class="flex flex-wrap items-center gap-2">
      <a href="{{ route('reports.credits.pdf', request()->all()) }}" target="_blank"
         class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-red-600 text-white hover:bg-red-500 transition">
        📄 Exportar PDF
      </a>

      <a href="{{ route('reports.credits.print', request()->all()) }}" target="_blank"
         class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-500 transition">
        🖨️ Imprimir
      </a>

      <a href="{{ route('dashboard.index') }}"
         class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-gray-800 border border-gray-700 text-gray-200 hover:bg-gray-700 transition">
        ⬅️ Volver
      </a>
    </div>
  </div>

  <x-flash-message />

  {{-- Filtros --}}
  <div class="bg-gray-900 text-white rounded-xl shadow-2xl border border-amber-400/40 mb-4">
    <form method="GET" action="{{ route('reports.credits') }}" class="p-4">
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-3 items-end">

        <div class="lg:col-span-4">
          <label class="block text-xs uppercase tracking-wide text-gray-400 mb-1">Buscar</label>
          <input name="q" type="text" value="{{ request('q') }}"
                 placeholder="Cliente, CI/RUC, venta…"
                 class="w-full rounded-lg bg-gray-800 border border-gray-700 focus:border-amber-400 focus:ring-0 px-3 py-2 text-sm placeholder-gray-500"/>
        </div>

        <div class="lg:col-span-3">
          <label class="block text-xs uppercase tracking-wide text-gray-400 mb-1">Estado</label>
          <select name="status"
                  class="w-full rounded-lg bg-gray-800 border border-gray-700 focus:border-amber-400 focus:ring-0 px-3 py-2 text-sm">
            <option value="">Todos</option>
            <option value="pendiente" {{ in_array(request('status'), ['pendiente','pending'], true) ? 'selected' : '' }}>Pendiente</option>
            <option value="vencido"   {{ in_array(request('status'), ['vencido','overdue'], true) ? 'selected' : '' }}>Vencido</option>
            <option value="pagado"    {{ in_array(request('status'), ['pagado','paid'], true) ? 'selected' : '' }}>Pagado</option>
          </select>
        </div>

        <div class="lg:col-span-2">
          <label class="block text-xs uppercase tracking-wide text-gray-400 mb-1">Desde</label>
          <input type="date" name="from" value="{{ request('from') }}"
                 class="w-full rounded-lg bg-gray-800 border border-gray-700 focus:border-amber-400 focus:ring-0 px-3 py-2 text-sm"/>
        </div>

        <div class="lg:col-span-2">
          <label class="block text-xs uppercase tracking-wide text-gray-400 mb-1">Hasta</label>
          <input type="date" name="to" value="{{ request('to') }}"
                 class="w-full rounded-lg bg-gray-800 border border-gray-700 focus:border-amber-400 focus:ring-0 px-3 py-2 text-sm"/>
        </div>

        <div class="lg:col-span-1 flex gap-2">
          <button type="submit"
                  class="w-full inline-flex items-center justify-center px-4 py-2 rounded-lg bg-amber-500/20 border border-amber-400 text-amber-300 hover:bg-amber-500/30 text-sm font-medium transition">
            Buscar
          </button>
          <a href="{{ route('reports.credits') }}"
             class="w-full inline-flex items-center justify-center px-4 py-2 rounded-lg bg-gray-800 border border-gray-700 text-gray-300 hover:bg-gray-700 text-sm font-medium transition">
            Limpiar
          </a>
        </div>

      </div>
    </form>
  </div>

  {{-- Tabla --}}
  <div class="bg-gray-900 text-white rounded-xl shadow-2xl border border-amber-400/40">
    <div class="relative max-w-full">

      <div class="max-w-full overflow-x-auto max-h-[70vh] rounded-t-xl">
        <table class="w-full text-sm text-left">
          <thead class="bg-gray-800 text-gray-200 uppercase text-xs tracking-wide sticky top-0 z-10">
            <tr>
              <th class="px-6 py-3 w-20">#</th>
              <th class="px-6 py-3">Cliente</th>
              <th class="px-6 py-3 text-right">Monto</th>
              <th class="px-6 py-3">Vencimiento</th>
              <th class="px-6 py-3">Estado</th>
            </tr>
          </thead>

          <tbody class="divide-y divide-gray-700">
            @forelse($credits as $c)
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

                $date = $c->due_date ?? null;
                $due = $date instanceof Carbon ? $date : (filled($date) ? Carbon::parse($date) : null);

                $amount = (float) ($c->amount ?? 0);
              @endphp

              <tr class="hover:bg-gray-800/60 transition">
                <td class="px-6 py-3 font-mono text-gray-300">#{{ $c->id }}</td>

                <td class="px-6 py-3">
                  <div class="font-semibold text-gray-100">
                    {{ $c->client->name ?? '—' }}
                  </div>
                  @php
                    $doc = $c->client->documento ?? $c->client->ruc ?? null;
                  @endphp
                  @if($doc)
                    <div class="text-[11px] text-gray-400 font-mono">
                      {{ $doc }}
                    </div>
                  @endif
                </td>

                <td class="px-6 py-3 text-right font-bold tabular-nums text-amber-300 whitespace-nowrap">
                  Gs. {{ number_format($amount, 0, ',', '.') }}
                </td>

                <td class="px-6 py-3 text-gray-300 whitespace-nowrap">
                  {{ $due?->format('Y-m-d') ?? '—' }}
                </td>

                <td class="px-6 py-3">
                  <x-status-badge :color="$color" :label="$label" />
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="5" class="px-6 py-12">
                  <div class="flex flex-col items-center justify-center text-center text-gray-400">
                    <div class="text-5xl mb-3">🗂️</div>
                    <p class="font-semibold">Sin créditos</p>
                    <p class="text-sm">No hay registros para mostrar con estos filtros.</p>
                  </div>
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      {{-- Totales --}}
      <div class="p-4 border-t border-gray-700 flex flex-col md:flex-row md:items-center md:justify-between gap-2">
        <div class="text-sm text-gray-400">
          Total listado: <span class="font-semibold text-gray-200">Gs. {{ number_format((float)$totalListado, 0, ',', '.') }}</span>
        </div>
        <div class="text-sm text-amber-300 font-semibold">
          Total pendiente: Gs. {{ number_format((float)$pendingTotal, 0, ',', '.') }}
        </div>
      </div>

      {{-- Paginación --}}
      @if($credits instanceof \Illuminate\Pagination\AbstractPaginator)
        <div class="p-4 border-t border-gray-700">
          {{ $credits->withQueryString()->links() }}
        </div>
      @endif

    </div>
  </div>

</div>
@endsection
