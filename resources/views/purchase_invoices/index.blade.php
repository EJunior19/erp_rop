@extends('layout.admin')

@section('title', 'Facturas de compra')

@section('content')
<div class="w-full px-6 space-y-6">

  {{-- Título + botón --}}
  <div class="flex items-center justify-between">
    <h1 class="text-2xl font-bold text-sky-400 flex items-center gap-2">
      🧾 Facturas de compra
    </h1>

    <a href="{{ route('purchase_invoices.create') }}"
       class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-sm font-semibold text-white shadow">
      <span class="text-lg leading-none">+</span>
      <span>Nueva factura</span>
    </a>
  </div>

  {{-- Mensaje de éxito --}}
  @if(session('success'))
    <div class="rounded-lg border border-emerald-500/60 bg-emerald-900/30 px-4 py-3 text-sm text-emerald-100">
      {{ session('success') }}
    </div>
  @endif

  {{-- Tabla de facturas --}}
  <div class="overflow-x-auto rounded-xl border border-gray-700 bg-gray-900 shadow">
    <table class="min-w-full text-sm">
      <thead class="bg-gray-800 text-xs uppercase text-gray-300">
        <tr>
          <th class="px-4 py-2 text-left">ID</th>
          <th class="px-4 py-2 text-left">Factura</th>
          <th class="px-4 py-2 text-left">Proveedor</th>
          <th class="px-4 py-2 text-left">OC / Recepción</th>
          <th class="px-4 py-2 text-left">Fecha</th>
          <th class="px-4 py-2 text-right">Total</th>
          <th class="px-4 py-2 text-left">Estado</th>
          <th class="px-4 py-2 text-right">Acciones</th>
        </tr>
      </thead>

      <tbody class="divide-y divide-gray-700">
        @forelse($invoices as $inv)
          @php
            $receipt  = $inv->receipt;
            $order    = $receipt?->order;
            $supplier = $order?->supplier;

            // Chip de estado con colores
            $status = strtoupper($inv->status ?? 'EMITIDA');
            $statusClasses = match($status) {
              'EMITIDA'   => 'bg-sky-700 text-sky-100',
              'PAGADA'    => 'bg-emerald-700 text-emerald-100',
              'ANULADA'   => 'bg-rose-700 text-rose-100',
              default     => 'bg-gray-700 text-gray-100',
            };
          @endphp

          <tr class="hover:bg-gray-800/60">
            {{-- ID --}}
            <td class="px-4 py-2 text-gray-200">
              #{{ $inv->id }}
            </td>

            {{-- Factura --}}
            <td class="px-4 py-2">
              <div class="font-semibold text-gray-100">
                {{ $inv->invoice_number }}
              </div>
              <div class="text-xs text-gray-400">
                {{-- Podés usar aquí timbrado / referencia si después agregás el campo --}}
                Timbrado / ref. opcional
              </div>
            </td>

            {{-- Proveedor --}}
            <td class="px-4 py-2">
              @if($supplier)
                <div class="font-medium text-gray-100">
                  {{ $supplier->name }}
                </div>
                <div class="text-xs text-gray-400">
                  RUC: {{ $supplier->ruc ?? '—' }} · ID: {{ $supplier->id }}
                </div>
              @else
                <span class="text-xs px-2 py-1 rounded bg-amber-700/40 text-amber-100">
                  Sin proveedor vinculado
                </span>
              @endif
            </td>

            {{-- OC / Recepción --}}
            <td class="px-4 py-2">
              @if($order)
                <div class="text-xs text-gray-300">
                  OC: <span class="font-mono text-sky-300">{{ $order->order_number }}</span>
                </div>
              @endif
              @if($receipt)
                <div class="text-xs text-gray-300">
                  RCP: <span class="font-mono text-sky-300">{{ $receipt->receipt_number }}</span>
                </div>
              @endif
              @unless($order || $receipt)
                <span class="text-xs text-gray-500">—</span>
              @endunless
            </td>

            {{-- Fecha --}}
            <td class="px-4 py-2 text-gray-200">
              {{ \Illuminate\Support\Carbon::parse($inv->invoice_date)->format('d/m/Y') }}
            </td>

            {{-- Total --}}
            <td class="px-4 py-2 text-right text-gray-100">
              {{ number_format($inv->total, 0, ',', '.') }} Gs.
            </td>

            {{-- Estado --}}
            <td class="px-4 py-2">
              <span class="px-2 py-1 text-xs rounded {{ $statusClasses }}">
                {{ $status }}
              </span>
            </td>

            {{-- Acciones --}}
            <td class="px-4 py-2 text-right">
              <div class="inline-flex gap-2">
                <a href="{{ route('purchase_invoices.show', $inv) }}"
                   class="px-3 py-1 rounded border border-sky-500/70 text-sky-200 hover:bg-sky-600/20 text-xs font-semibold">
                  Ver detalle
                </a>
                {{-- Más adelante: botón para editar / imprimir / exportar --}}
              </div>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="8" class="px-4 py-6 text-center text-gray-400">
              No hay facturas registradas todavía.
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  {{-- Paginación --}}
  @if ($invoices->hasPages())
    <div class="mt-4">
      {{ $invoices->links() }}
    </div>
  @endif
</div>
@endsection
