@extends('layout.admin')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-6 text-white">

  {{-- Título + volver --}}
  <div class="flex items-center justify-between mb-4">
    <h1 class="text-2xl font-semibold flex items-center gap-2">
      🧾 Factura de compra {{ $purchase_invoice->invoice_number }}
    </h1>

    <a href="{{ route('purchase_invoices.index') }}"
       class="inline-flex items-center text-sm text-gray-300 hover:text-white">
      ← Volver al listado
    </a>
  </div>

  @if(session('success'))
    <div class="bg-green-100 text-green-800 border border-green-300 rounded px-3 py-2 mb-4 text-sm">
      {{ session('success') }}
    </div>
  @endif

  @php
    $invoice  = $purchase_invoice;
    $receipt  = $invoice->receipt ?? null;
    $order    = $receipt?->order;
    $supplier = $order?->supplier;
  @endphp

  {{-- 1) Resumen principal: Proveedor / Factura / Totales --}}
  <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    {{-- Proveedor --}}
    <div class="bg-gray-800 rounded shadow p-4 text-sm md:col-span-2">
      <h3 class="text-sm font-semibold mb-2 text-emerald-400">Proveedor</h3>

      @if($supplier)
        <div class="font-semibold">{{ $supplier->name }}</div>
        @if(!empty($supplier->ruc))
          <div class="text-gray-300 text-xs">RUC: {{ $supplier->ruc }}</div>
        @endif
        <div class="text-gray-400 text-xs mt-1">ID proveedor: {{ $supplier->id }}</div>
      @else
        <span class="text-yellow-300 text-sm">Sin proveedor vinculado</span>
      @endif
    </div>

    {{-- Factura --}}
    <div class="bg-gray-800 rounded shadow p-4 text-sm">
      <h3 class="text-sm font-semibold mb-2 text-emerald-400">Factura</h3>

      <div><span class="font-semibold">Número:</span> {{ $invoice->invoice_number }}</div>
      <div class="text-gray-300 text-xs">
        Fecha: {{ \Illuminate\Support\Carbon::parse($invoice->invoice_date)->format('d/m/Y') }}
      </div>
      <div class="text-gray-300 text-xs mt-1">
        Estado:
        <span class="px-1.5 py-0.5 rounded text-[11px]
          @switch($invoice->status)
            @case('emitida') bg-sky-900/40 border border-sky-600/50 text-sky-300 @break
            @case('anulada') bg-red-900/40 border border-red-600/50 text-red-300 @break
            @default        bg-gray-700/60 border border-gray-500/60 text-gray-200
          @endswitch">
          {{ strtoupper($invoice->status) }}
        </span>
      </div>
      @if($invoice->created_by)
        <div class="text-gray-300 text-xs mt-1">
          Creado por: {{ $invoice->creator->name ?? $invoice->created_by }}
        </div>
      @endif
      @if($invoice->notes)
        <div class="text-gray-300 text-xs mt-2">
          <span class="font-semibold">Notas:</span> {{ $invoice->notes }}
        </div>
      @endif
    </div>

    {{-- Totales --}}
    <div class="bg-gray-800 rounded shadow p-4 text-sm">
      <h3 class="text-sm font-semibold mb-2 text-emerald-400">Totales</h3>

      <div class="flex justify-between text-xs mb-1">
        <span>Subtotal:</span>
        <span class="font-semibold">
          {{ number_format($invoice->subtotal, 0, ',', '.') }} Gs
        </span>
      </div>
      <div class="flex justify-between text-xs mb-1">
        <span>IVA:</span>
        <span class="font-semibold">
          {{ number_format($invoice->tax, 0, ',', '.') }} Gs
        </span>
      </div>
      <div class="flex justify-between text-sm mt-2 border-t border-gray-700 pt-2">
        <span>Total:</span>
        <span class="font-bold text-emerald-400">
          {{ number_format($invoice->total, 0, ',', '.') }} Gs
        </span>
      </div>
    </div>
  </div>

  {{-- 2) Orden de compra y recepción --}}
  <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
    <div class="bg-gray-800 rounded shadow p-4 text-sm">
      <h3 class="text-sm font-semibold mb-2 text-emerald-400">Orden de compra</h3>

      @if($order)
        <div><span class="font-semibold">OC:</span> {{ $order->order_number }}</div>
        <div class="text-gray-300 text-xs">
          Fecha: {{ \Illuminate\Support\Carbon::parse($order->order_date)->format('d/m/Y') }}
        </div>
        <div class="text-gray-300 text-xs">
          Estado: <span class="uppercase">{{ $order->status }}</span>
        </div>
      @else
        <span class="text-yellow-300 text-sm">Sin OC vinculada</span>
      @endif
    </div>

    <div class="bg-gray-800 rounded shadow p-4 text-sm">
      <h3 class="text-sm font-semibold mb-2 text-emerald-400">Recepción</h3>

      @if($receipt)
        <div><span class="font-semibold">RCP:</span> {{ $receipt->receipt_number }}</div>
        <div class="text-gray-300 text-xs">
          Recibida: {{ \Illuminate\Support\Carbon::parse($receipt->received_date)->format('d/m/Y') }}
        </div>
        <div class="text-gray-300 text-xs">
          Estado: <span class="uppercase">{{ $receipt->status }}</span>
        </div>
      @else
        <span class="text-yellow-300 text-sm">Sin recepción vinculada</span>
      @endif
    </div>
  </div>

  {{-- 3) Ítems de la factura --}}
  <div class="bg-gray-800 rounded shadow mb-6">
    <div class="flex items-center justify-between px-4 py-3 border-b border-gray-700">
      <h3 class="text-sm font-semibold text-emerald-400">
        Ítems de la factura
      </h3>
      <span class="text-[11px] text-gray-300">
        Detalle de cantidades, costos e impuestos.
      </span>
    </div>

    <div class="overflow-x-auto">
      <table class="min-w-full text-sm text-left">
        <thead class="bg-gray-900 text-gray-200 text-xs uppercase">
          <tr>
            <th class="px-3 py-2">#</th>
            <th class="px-3 py-2">Producto</th>
            <th class="px-3 py-2 text-right">Cant.</th>
            <th class="px-3 py-2 text-right">Costo unit.</th>
            <th class="px-3 py-2 text-right">IVA %</th>
            <th class="px-3 py-2 text-right">Subtotal</th>
            <th class="px-3 py-2 text-right">IVA</th>
            <th class="px-3 py-2 text-right">Total</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-700 bg-gray-900">
          @forelse($invoice->items as $idx => $item)
            <tr>
              <td class="px-3 py-2">{{ $idx + 1 }}</td>
              <td class="px-3 py-2">
                <div class="font-semibold">
                  {{ $item->product?->name ?? '[Producto]' }}
                </div>
                <div class="text-xs text-gray-300">
                  Código: {{ $item->product?->code ?? 'N/D' }}
                </div>
              </td>
              <td class="px-3 py-2 text-right">
                {{ number_format($item->qty, 0, ',', '.') }}
              </td>
              <td class="px-3 py-2 text-right">
                {{ number_format($item->unit_cost, 0, ',', '.') }} Gs
              </td>
              <td class="px-3 py-2 text-right">
                {{ number_format($item->tax_rate, 0) }}%
              </td>
              <td class="px-3 py-2 text-right">
                {{ number_format($item->subtotal, 0, ',', '.') }} Gs
              </td>
              <td class="px-3 py-2 text-right">
                {{ number_format($item->tax, 0, ',', '.') }} Gs
              </td>
              <td class="px-3 py-2 text-right">
                {{ number_format($item->total, 0, ',', '.') }} Gs
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="8" class="px-3 py-4 text-center text-gray-300">
                Esta factura no tiene ítems cargados.
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{-- Más adelante acá podemos meter el bloque "Cuenta por pagar" cuando
       agreguemos la relación $invoice->payable en el modelo. --}}
</div>
@endsection
