{{-- resources/views/purchase_receipts/index.blade.php --}}
@extends('layout.admin')

@section('content')
<div class="w-full px-6 text-gray-200">

  {{-- Header --}}
  <div class="flex items-center justify-between mb-6">
    <h1 class="text-3xl font-bold text-sky-400 flex items-center gap-2">
      🚛 Recepciones de compra
    </h1>

    @if (Route::has('purchase_receipts.create'))
      <a href="{{ route('purchase_receipts.create') }}"
         class="px-4 py-2 rounded-lg bg-sky-600 hover:bg-sky-700 font-semibold shadow">
        ➕ Nueva recepción
      </a>
    @endif
  </div>

  <x-flash-message />

  {{-- Filtros --}}
  <form method="GET" action="{{ route('purchase_receipts.index') }}"
        class="bg-gray-900 border border-gray-700 rounded-xl p-4 mb-4">
    <div class="grid md:grid-cols-4 gap-3">
      {{-- Buscar --}}
      <div>
        <label class="block text-xs text-gray-400 mb-1">Buscar</label>
        <input type="text"
               name="q"
               value="{{ request('q') }}"
               placeholder="N° recepción, N° OC o proveedor…"
               class="w-full rounded-lg border-gray-700 bg-gray-800 text-gray-100 px-3 py-2">
      </div>

      {{-- Estado --}}
      <div>
        <label class="block text-xs text-gray-400 mb-1">Estado</label>
        @php $st = request('status'); @endphp
        <select name="status"
                class="w-full rounded-lg border-gray-700 bg-gray-800 text-gray-100 px-3 py-2">
          <option value="">Todos</option>
          @foreach (['borrador','pendiente_aprobacion','aprobado','rechazado'] as $opt)
            <option value="{{ $opt }}" @selected($st === $opt)">
              {{ ucfirst(str_replace('_',' ',$opt)) }}
            </option>
          @endforeach
        </select>
      </div>

      {{-- Por página --}}
      <div>
        <label class="block text-xs text-gray-400 mb-1">Por página</label>
        @php $pp = (int) request('per_page', 15); @endphp
        <select name="per_page"
                class="w-full rounded-lg border-gray-700 bg-gray-800 text-gray-100 px-3 py-2">
          @foreach ([10,15,25,50] as $n)
            <option value="{{ $n }}" @selected($pp === $n)>{{ $n }}</option>
          @endforeach
        </select>
      </div>

      {{-- Botón Filtrar --}}
      <div class="flex items-end">
        <button class="w-full px-4 py-2 rounded-lg bg-sky-600 hover:bg-sky-700 text-white font-semibold shadow">
          Filtrar
        </button>
      </div>
    </div>

    <div class="mt-3 flex items-center gap-3">
      @if(
        request()->filled('q') ||
        request()->filled('status') ||
        request()->filled('per_page')
      )
        <a href="{{ route('purchase_receipts.index') }}"
           class="px-4 py-2 rounded-lg border border-gray-600 text-gray-300 hover:bg-gray-700 font-semibold">
          Limpiar
        </a>
      @endif

      <span class="ml-auto text-sm text-gray-400">
        Mostrando
        <span class="text-gray-200 font-semibold">{{ $receipts->count() }}</span>
        de
        <span class="text-gray-200 font-semibold">{{ $receipts->total() }}</span>
        registros
      </span>
    </div>
  </form>

  {{-- Tabla --}}
  <div class="bg-gray-900 border border-gray-700 rounded-xl overflow-hidden">
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-800 text-gray-300 uppercase text-xs">
          <tr>
            <th class="px-4 py-2 text-left">#</th>
            <th class="px-4 py-2 text-left">N° recepción</th>
            <th class="px-4 py-2 text-left">Orden de compra</th>
            <th class="px-4 py-2 text-left">Proveedor</th>
            <th class="px-4 py-2 text-left">Fecha recibida</th>
            <th class="px-4 py-2 text-left">Ítems</th>
            <th class="px-4 py-2 text-left">Estado</th>
            <th class="px-4 py-2 text-right">Acciones</th>
          </tr>
        </thead>

        <tbody class="divide-y divide-gray-700 text-gray-200">
          @forelse ($receipts as $r)
            @php
              $chip = [
                'borrador'              => 'bg-gray-700 text-gray-300 border border-gray-500',
                'pendiente_aprobacion'  => 'bg-amber-600 text-white border border-amber-400',
                'aprobado'              => 'bg-emerald-600 text-white border border-emerald-400',
                'rechazado'             => 'bg-rose-600 text-white border border-rose-400',
              ][$r->status] ?? 'bg-gray-700 text-gray-300 border border-gray-500';
            @endphp
            <tr class="hover:bg-gray-800/60">
              <td class="px-4 py-2 text-gray-300">
                {{ $r->id }}
              </td>

              <td class="px-4 py-2 font-mono text-sky-300">
                {{ $r->receipt_number }}
              </td>

              <td class="px-4 py-2">
                @if($r->order)
                  <a href="{{ route('purchase_orders.show', $r->order) }}"
                     class="text-sky-400 hover:text-sky-300 underline">
                    {{ $r->order->order_number }}
                  </a>
                @else
                  —
                @endif
              </td>

              <td class="px-4 py-2">
                {{ $r->order?->supplier?->name ?? '—' }}
              </td>

              <td class="px-4 py-2">
                {{ \Illuminate\Support\Carbon::parse($r->received_date)->format('d/m/Y') }}
              </td>

              <td class="px-4 py-2 text-gray-300">
                {{ (int) $r->items_count }}
              </td>

              <td class="px-4 py-2">
                <span class="px-2 py-1 text-xs rounded-lg font-semibold {{ $chip }}">
                  {{ ucfirst(str_replace('_',' ',$r->status)) }}
                </span>
              </td>

              <td class="px-4 py-2 text-right">
                <div class="inline-flex gap-2">

                  {{-- Ver --}}
                  <a href="{{ route('purchase_receipts.show', $r) }}"
                     class="px-3 py-1 rounded-lg text-xs
                            border border-gray-500 text-gray-200
                            hover:bg-gray-700 hover:text-white transition">
                    👁 Ver
                  </a>

                  {{-- Ticket (solo aprobados) --}}
                  @if ($r->status === 'aprobado')
                    <a href="{{ route('purchase_receipts.ticket', $r) }}"
                       target="_blank"
                       class="px-3 py-1 rounded-lg text-xs font-semibold
                              border border-emerald-400 text-emerald-300
                              hover:bg-emerald-400 hover:text-gray-900
                              transition">
                      🧾 Ticket
                    </a>
                  @endif

                  {{-- Aprobar / Rechazar (pendiente_aprobacion) --}}
                  @if ($r->status === 'pendiente_aprobacion')

                    <x-confirm-button
                      :action="route('purchase_receipts.approve', $r)"
                      label="Aprobar"
                      variant="success"
                      swal-title="¿Aprobar recepción?"
                      swal-text="El stock será actualizado."
                      swal-icon="question"
                      class="!bg-emerald-600 !hover:bg-emerald-700 !text-white"
                    />

                    <x-confirm-button
                      :action="route('purchase_receipts.reject', $r)"
                      label="Rechazar"
                      variant="danger"
                      swal-title="¿Rechazar recepción?"
                      swal-text="Volverá al estado rechazado."
                      swal-icon="warning"
                      class="!bg-rose-600 !hover:bg-rose-700 !text-white"
                    />

                  @endif
                </div>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="8" class="px-4 py-8 text-center text-gray-400">
                No se encontraron recepciones con los filtros actuales.
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    {{-- Footer tabla --}}
    <div class="flex items-center justify-between px-4 py-3 border-t border-gray-700 text-sm">
      @php
        $pageItems = $receipts->sum('items_count');
      @endphp
      <div class="text-gray-300">
        Ítems recibidos en esta página:
        <span class="text-sky-300 font-bold">{{ number_format($pageItems, 0, ',', '.') }}</span>
      </div>
      <div>
        {{ $receipts->onEachSide(1)->links() }}
      </div>
    </div>
  </div>

</div>
@endsection
