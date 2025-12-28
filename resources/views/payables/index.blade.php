@extends('layout.admin')

@section('content')
<div class="w-full min-h-screen space-y-8 bg-[#0b0d10]">

  {{-- HEADER --}}
  <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
    <div>
      <h1 class="text-3xl font-extrabold text-sky-400 flex items-center gap-2">
        📑 Cuentas por pagar
      </h1>
      <p class="text-sm text-gray-400 mt-1">
        Control de facturas de proveedores, vencimientos y saldos pendientes.
      </p>
    </div>
  </div>

  {{-- RESUMEN NUMÉRICO --}}
  @php
    $pageTotal   = $payables->sum('total_amount');
    $pagePaid    = $payables->sum('paid_amount');
    $pagePending = $payables->sum('pending_amount');
  @endphp

  <div class="grid md:grid-cols-3 gap-4">
    <div class="bg-[#0f1114] border border-gray-700 rounded-xl p-5 hover:border-gray-500 transition">
      <p class="text-xs text-gray-400 uppercase">Total facturado</p>
      <p class="mt-2 text-2xl font-semibold text-gray-100 tabular-nums">
        {{ number_format($pageTotal, 0, ',', '.') }} Gs.
      </p>
    </div>

    <div class="bg-[#0f1114] border border-gray-700 rounded-xl p-5 hover:border-emerald-500/60 transition">
      <p class="text-xs text-gray-400 uppercase">Pagado</p>
      <p class="mt-2 text-2xl font-semibold text-emerald-400 tabular-nums">
        {{ number_format($pagePaid, 0, ',', '.') }} Gs.
      </p>
    </div>

    <div class="bg-[#0f1114] border border-gray-700 rounded-xl p-5 hover:border-amber-500/60 transition">
      <p class="text-xs text-gray-400 uppercase">Pendiente</p>
      <p class="mt-2 text-2xl font-semibold text-amber-400 tabular-nums">
        {{ number_format($pagePending, 0, ',', '.') }} Gs.
      </p>
    </div>
  </div>

  {{-- FILTROS --}}
  <form method="GET" class="bg-[#0f1114] border border-gray-700 rounded-xl p-6 space-y-4">
    <h2 class="text-sm font-semibold text-gray-300">🔎 Filtros</h2>

    <div class="grid md:grid-cols-5 gap-4">
      <div class="md:col-span-2">
        <label class="text-xs text-gray-400">Proveedor</label>
        <input type="text" name="supplier" value="{{ $supplier }}"
          class="w-full mt-1 rounded-lg bg-[#1a1d22] border border-gray-700 text-gray-100 px-3 py-2 focus:ring-2 focus:ring-sky-600 focus:outline-none">
      </div>

      <div>
        <label class="text-xs text-gray-400">Estado</label>
        <select name="status"
          class="w-full mt-1 rounded-lg bg-[#1a1d22] border border-gray-700 text-gray-100 px-3 py-2 focus:ring-2 focus:ring-sky-600 focus:outline-none">
          <option value="">Todos</option>
          <option value="pendiente" @selected($status === 'pendiente')>Pendiente</option>
          <option value="parcial"   @selected($status === 'parcial')>Parcial</option>
          <option value="pagado"    @selected($status === 'pagado')>Pagado</option>
        </select>
      </div>

      <div>
        <label class="text-xs text-gray-400">Desde</label>
        <input type="date" name="from" value="{{ $from }}"
          class="w-full mt-1 rounded-lg bg-[#1a1d22] border border-gray-700 text-gray-100 px-3 py-2 focus:ring-2 focus:ring-sky-600 focus:outline-none">
      </div>

      <div>
        <label class="text-xs text-gray-400">Hasta</label>
        <input type="date" name="to" value="{{ $to }}"
          class="w-full mt-1 rounded-lg bg-[#1a1d22] border border-gray-700 text-gray-100 px-3 py-2 focus:ring-2 focus:ring-sky-600 focus:outline-none">
      </div>
    </div>

    <div class="flex justify-end gap-2 pt-2">
      <button class="px-5 py-2 bg-sky-600 hover:bg-sky-700 rounded-lg text-white font-semibold transition">
        Aplicar
      </button>
      <a href="{{ route('payables.index') }}"
        class="px-5 py-2 border border-gray-600 rounded-lg text-gray-300 hover:bg-gray-800 transition">
        Limpiar
      </a>
    </div>
  </form>

  {{-- TABLA --}}
  <div class="overflow-x-auto rounded-xl border border-gray-700 bg-[#0d0f12]">
    <table class="min-w-full text-sm">
      <thead class="bg-[#111317] text-gray-300 uppercase text-xs border-b border-gray-700">
        <tr>
          <th class="px-4 py-3 text-left">Proveedor</th>
          <th class="px-4 py-3 text-left">Factura</th>
          <th class="px-4 py-3 text-right">Total</th>
          <th class="px-4 py-3 text-right">Pagado</th>
          <th class="px-4 py-3 text-right">Pendiente</th>
          <th class="px-4 py-3 text-left">Estado</th>
          <th class="px-4 py-3 text-center">Acciones</th>
        </tr>
      </thead>

      <tbody class="divide-y divide-gray-800">
        @forelse($payables as $p)
          <tr class="hover:bg-[#1a1d22] transition">
            <td class="px-4 py-3 text-gray-100">
              {{ $p->supplier->name ?? '—' }}
            </td>

            <td class="px-4 py-3 text-gray-300">
              {{ $p->invoice->invoice_number ?? '—' }}
            </td>

            <td class="px-4 py-3 text-right text-gray-100 tabular-nums">
              {{ number_format($p->total_amount, 0, ',', '.') }} Gs.
            </td>

            <td class="px-4 py-3 text-right text-emerald-300 tabular-nums">
              {{ number_format($p->paid_amount, 0, ',', '.') }} Gs.
            </td>

            <td class="px-4 py-3 text-right text-amber-300 tabular-nums">
              {{ number_format($p->pending_amount, 0, ',', '.') }} Gs.
            </td>

            <td class="px-4 py-3 text-xs">
              <span class="px-2 py-1 rounded border
                @if($p->status === 'pagado') bg-emerald-700/70 border-emerald-500 text-emerald-100
                @elseif($p->status === 'parcial') bg-yellow-700/70 border-yellow-500 text-yellow-100
                @else bg-red-700/70 border-red-500 text-red-100
                @endif">
                {{ ucfirst($p->status) }}
              </span>
              <td class="px-4 py-3 text-center">
              <div class="flex justify-center gap-2">

                {{-- VER --}}
                <a href="{{ route('payables.show', $p) }}"
                  class="inline-flex items-center gap-1 px-3 py-1.5 rounded-md
                          bg-sky-600/80 hover:bg-sky-600
                          text-white text-xs font-medium transition">
                  👁 Ver
                </a>

                {{-- APLICAR PAGO --}}
                @if($p->pending_amount > 0)
                  <a href="{{ route('payables.show', $p) }}#registrar-pago"
                    class="inline-flex items-center gap-1 px-3 py-1.5 rounded-md
                            bg-emerald-600/80 hover:bg-emerald-600
                            text-white text-xs font-medium transition">
                    💵 Pagar
                  </a>
                @endif

              </div>
            </td>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="6" class="px-4 py-10 text-center text-gray-500">
              No se encontraron registros.
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  {{-- PAGINACIÓN --}}
  <div class="pt-4">
    {{ $payables->links() }}
  </div>

</div>
@endsection
