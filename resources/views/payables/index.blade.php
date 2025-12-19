@extends('layout.admin')

@section('content')
<div class="w-full px-6 py-6 space-y-6">

  {{-- HEADER --}}
  <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
    <div>
      <h1 class="text-3xl font-bold text-sky-400 flex items-center gap-2">
        📑 Cuentas por pagar a proveedores
      </h1>
      <p class="text-sm text-gray-400 mt-1">
        Control de facturas de proveedores, vencimientos y saldos pendientes.
      </p>
    </div>

    {{-- Acción principal opcional (ej: volver al dashboard) --}}
    {{-- 
    <a href="{{ route('dashboard') }}"
       class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-gray-800 hover:bg-gray-700 border border-gray-600 text-sm text-gray-100 shadow-sm transition">
      ⬅️ Volver al panel
    </a>
    --}}
  </div>

  {{-- RESUMEN NUMÉRICO (solo página actual) --}}
  @php
    $pageTotal   = $payables->sum('total_amount');
    $pagePaid    = $payables->sum('paid_amount');
    $pagePending = $payables->sum('pending_amount');
  @endphp

  <div class="grid md:grid-cols-3 gap-4">
    <div class="bg-[#0f1114] border border-gray-700 rounded-xl p-4 shadow">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-xs text-gray-400 uppercase tracking-wide">Total facturado (página)</p>
          <p class="mt-1 text-xl font-semibold text-gray-100">
            {{ number_format($pageTotal, 0, ',', '.') }} Gs.
          </p>
        </div>
        <div class="h-10 w-10 rounded-full bg-sky-900/40 flex items-center justify-center text-sky-300 text-lg">
          ₲
        </div>
      </div>
    </div>

    <div class="bg-[#0f1114] border border-gray-700 rounded-xl p-4 shadow">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-xs text-gray-400 uppercase tracking-wide">Pagado (página)</p>
          <p class="mt-1 text-xl font-semibold text-emerald-300">
            {{ number_format($pagePaid, 0, ',', '.') }} Gs.
          </p>
        </div>
        <div class="h-10 w-10 rounded-full bg-emerald-900/30 flex items-center justify-center text-emerald-300 text-lg">
          ✔
        </div>
      </div>
    </div>

    <div class="bg-[#0f1114] border border-gray-700 rounded-xl p-4 shadow">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-xs text-gray-400 uppercase tracking-wide">Pendiente (página)</p>
          <p class="mt-1 text-xl font-semibold {{ $pagePending > 0 ? 'text-yellow-300' : 'text-gray-100' }}">
            {{ number_format($pagePending, 0, ',', '.') }} Gs.
          </p>
        </div>
        <div class="h-10 w-10 rounded-full bg-amber-900/30 flex items-center justify-center text-amber-300 text-lg">
          ⏱
        </div>
      </div>
    </div>
  </div>

  {{-- FILTROS --}}
  <form method="GET" class="bg-[#0f1114] border border-gray-700 rounded-xl p-5 shadow-lg space-y-4">
    <div class="flex items-center justify-between gap-3">
      <h2 class="text-sm font-semibold text-gray-300 flex items-center gap-2">
        🔎 Filtros de búsqueda
      </h2>

      {{-- Chips de filtros activos --}}
      <div class="flex flex-wrap gap-2 text-xs">
        @if($supplier)
          <span class="inline-flex items-center px-2 py-1 rounded-full bg-sky-900/40 border border-sky-600 text-sky-200">
            Proveedor: <span class="ml-1 font-semibold">{{ $supplier }}</span>
          </span>
        @endif
        @if($status)
          <span class="inline-flex items-center px-2 py-1 rounded-full bg-gray-800 border border-gray-600 text-gray-200">
            Estado: <span class="ml-1 font-semibold">{{ ucfirst($status) }}</span>
          </span>
        @endif
        @if($from || $to)
          <span class="inline-flex items-center px-2 py-1 rounded-full bg-gray-800 border border-gray-600 text-gray-200">
            Vencimiento:
            <span class="ml-1 font-semibold">
              {{ $from ?: '—' }} → {{ $to ?: '—' }}
            </span>
          </span>
        @endif
      </div>
    </div>

    <div class="grid md:grid-cols-5 gap-4">
      <div class="md:col-span-2">
        <label class="text-xs text-gray-400 font-semibold">Proveedor</label>
        <input type="text" name="supplier" value="{{ $supplier }}"
               placeholder="Nombre o RUC del proveedor"
               class="w-full mt-1 rounded-lg bg-[#1a1d22] border border-gray-700 text-gray-100 px-3 py-2 focus:ring-2 focus:ring-sky-500 focus:outline-none">
      </div>

      <div>
        <label class="text-xs text-gray-400 font-semibold">Estado</label>
        <select name="status"
                class="w-full mt-1 rounded-lg bg-[#1a1d22] border border-gray-700 text-gray-100 px-3 py-2 focus:ring-2 focus:ring-sky-500 focus:outline-none">
          <option value="">Todos</option>
          @foreach (['pendiente' => 'Pendiente', 'parcial' => 'Pagado parcial', 'pagado' => 'Pagado'] as $key => $label)
            <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
          @endforeach
        </select>
      </div>

      <div>
        <label class="text-xs text-gray-400 font-semibold">Vencimiento desde</label>
        <input type="date" name="from" value="{{ $from }}"
               class="w-full mt-1 rounded-lg bg-[#1a1d22] border border-gray-700 text-gray-100 px-3 py-2 focus:ring-2 focus:ring-sky-500 focus:outline-none">
      </div>

      <div>
        <label class="text-xs text-gray-400 font-semibold">Vencimiento hasta</label>
        <input type="date" name="to" value="{{ $to }}"
               class="w-full mt-1 rounded-lg bg-[#1a1d22] border border-gray-700 text-gray-100 px-3 py-2 focus:ring-2 focus:ring-sky-500 focus:outline-none">
      </div>
    </div>

    <div class="flex flex-col md:flex-row md:justify-end mt-2 gap-3">
      <div class="flex gap-2">
        <button class="px-5 py-2 rounded-lg bg-sky-600 hover:bg-sky-700 text-white font-semibold shadow-md flex items-center gap-2">
          <span class="text-sm">Aplicar filtros</span>
        </button>

        <a href="{{ route('payables.index') }}"
           class="px-5 py-2 rounded-lg border border-gray-600 text-gray-200 hover:bg-gray-800 font-semibold flex items-center gap-2">
          Limpiar
        </a>
      </div>
    </div>
  </form>

  {{-- TABLA --}}
  <div class="overflow-x-auto rounded-xl border border-gray-700 shadow-xl bg-[#0d0f12]">
    <table class="min-w-full text-sm">
      <thead class="bg-[#111317] text-gray-200 uppercase text-xs border-b border-gray-700 sticky top-0 z-10">
        <tr>
          <th class="px-4 py-3 text-left">#</th>
          <th class="px-4 py-3 text-left">Proveedor</th>
          <th class="px-4 py-3 text-left">Factura</th>
          <th class="px-4 py-3 text-left">Recepción</th>
          <th class="px-4 py-3 text-left">Condición</th>
          <th class="px-4 py-3 text-left">Vence</th>
          <th class="px-4 py-3 text-right">Total</th>
          <th class="px-4 py-3 text-right">Pagado</th>
          <th class="px-4 py-3 text-right">Pendiente</th>
          <th class="px-4 py-3 text-left">Estado</th>
          <th class="px-4 py-3 text-right">Acciones</th>
        </tr>
      </thead>

      <tbody class="divide-y divide-gray-800">
        @forelse ($payables as $p)
          @php
            $statusChip = [
              'pendiente' => 'bg-red-700/80 text-red-100 border border-red-500/80',
              'parcial'   => 'bg-yellow-700/80 text-yellow-100 border border-yellow-500/80',
              'pagado'    => 'bg-emerald-700/80 text-emerald-100 border border-emerald-500/80',
            ][$p->status] ?? 'bg-gray-700 text-gray-100 border border-gray-500/70';

            $termLabel = match($p->payment_term) {
              'contado'      => 'Contado',
              'credito_30'   => 'Crédito 30 días',
              'credito_60'   => 'Crédito 60 días',
              'credito_90'   => 'Crédito 90 días',
              'zafra'        => 'Plan zafra',
              'especial'     => 'Especial',
              default        => $p->payment_term,
            };

            $rowBg = $p->is_overdue && $p->pending_amount > 0
              ? 'bg-red-950/40'
              : 'hover:bg-[#1a1d22]';
          @endphp

          <tr class="transition {{ $rowBg }}">
            <td class="px-4 py-3 text-gray-300 align-top">
              {{ $p->id }}
            </td>

            <td class="px-4 py-3 align-top">
              <div class="font-semibold text-gray-100 truncate max-w-xs">
                {{ $p->supplier->name ?? '—' }}
              </div>
              @if($p->supplier?->ruc)
                <div class="text-xs text-gray-500">
                  RUC: {{ $p->supplier->ruc }}
                </div>
              @endif
            </td>

            <td class="px-4 py-3 align-top">
              @if($p->invoice)
                <div class="font-mono text-gray-100 text-xs">
                  {{ $p->invoice->invoice_number }}
                </div>
                @if($p->invoice->invoice_date)
                  <div class="text-xs text-gray-500">
                    {{ \Illuminate\Support\Carbon::parse($p->invoice->invoice_date)->format('d/m/Y') }}
                  </div>
                @endif
              @else
                <span class="text-gray-500 text-xs">—</span>
              @endif
            </td>

            <td class="px-4 py-3 align-top">
              @if(optional($p->invoice)->receipt)
                <div class="font-mono text-sky-400 text-xs">
                  {{ $p->invoice->receipt->receipt_number }}
                </div>
                <div class="text-xs text-gray-500">
                  OC: {{ $p->invoice->receipt->order->order_number ?? '—' }}
                </div>
              @else
                <span class="text-gray-500 text-xs">—</span>
              @endif
            </td>

            <td class="px-4 py-3 text-gray-200 align-top text-xs">
              {{ $termLabel ?: '—' }}
            </td>

            <td class="px-4 py-3 text-gray-200 align-top text-xs">
              @if($p->due_date)
                <div class="flex flex-col">
                  <span>
                    {{ $p->due_date->format('d/m/Y') }}
                  </span>
                  @if($p->is_overdue && $p->pending_amount > 0)
                    <span class="mt-1 inline-flex items-center gap-1 text-[11px] text-red-400 font-semibold">
                      ⚠ Vencida
                    </span>
                  @endif
                </div>
              @else
                —
              @endif
            </td>

            <td class="px-4 py-3 text-right text-gray-100 align-top text-xs whitespace-nowrap">
              {{ number_format($p->total_amount, 0, ',', '.') }} Gs.
            </td>

            <td class="px-4 py-3 text-right text-emerald-300 align-top text-xs whitespace-nowrap">
              {{ number_format($p->paid_amount, 0, ',', '.') }} Gs.
            </td>

            <td class="px-4 py-3 text-right align-top text-xs whitespace-nowrap {{ $p->pending_amount > 0 ? 'text-yellow-300' : 'text-gray-300' }}">
              {{ number_format($p->pending_amount, 0, ',', '.') }} Gs.
            </td>

            <td class="px-4 py-3 align-top">
              <span class="inline-flex items-center px-2 py-1 text-[11px] rounded-lg font-semibold {{ $statusChip }}">
                {{ ucfirst($p->status) }}
              </span>
            </td>

            <td class="px-4 py-3 text-right align-top">
              <a href="{{ route('payables.show', $p) }}"
                 class="inline-flex items-center px-3 py-1.5 rounded-lg border border-sky-600 text-sky-400 hover:bg-sky-700 hover:text-white text-xs font-semibold transition">
                Ver detalle
              </a>
            </td>
          </tr>

        @empty
          <tr>
            <td colspan="11" class="px-4 py-8 text-center text-gray-500 text-sm">
              No se encontraron cuentas por pagar con los filtros seleccionados.
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  {{-- PAGINACIÓN --}}
  <div class="mt-6 flex justify-between items-center text-xs text-gray-400 flex-col md:flex-row gap-3">
    <div>
      @if($payables->total() > 0)
        Mostrando
        <span class="font-semibold text-gray-200">{{ $payables->firstItem() }}</span>
        a
        <span class="font-semibold text-gray-200">{{ $payables->lastItem() }}</span>
        de
        <span class="font-semibold text-gray-200">{{ $payables->total() }}</span>
        registros.
      @else
        Sin registros para mostrar.
      @endif
    </div>
    <div>
      {{ $payables->links() }}
    </div>
  </div>
</div>
@endsection
