{{-- resources/views/purchase_receipts/show.blade.php --}}
@extends('layout.admin')

@section('content')
<div class="p-6 text-gray-200">

  {{-- Encabezado + acciones --}}
  <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <h1 class="text-2xl font-bold text-green-400 flex items-center gap-3">
      🚚 Recepción #{{ $purchase_receipt->id }}
      <span class="text-sm px-2 py-0.5 rounded font-semibold
        @class([
          'bg-yellow-900/40 text-yellow-300 border border-yellow-700/50'   => $purchase_receipt->status === 'pendiente_aprobacion',
          'bg-emerald-900/40 text-emerald-300 border border-emerald-700/50'=> $purchase_receipt->status === 'aprobado',
          'bg-red-900/40 text-red-300 border red-700/50'                   => $purchase_receipt->status === 'rechazado',
          'bg-slate-800 text-slate-300 border border-slate-700/50'         => $purchase_receipt->status === 'borrador',
        ])">
        {{ ucfirst(str_replace('_',' ', $purchase_receipt->status)) }}
      </span>
    </h1>

    <x-action-buttons
      :back="route('purchase_receipts.index')"
      :edit="false"
      :delete="false">

      {{-- Enlace a la OC al costado --}}
      @if($purchase_receipt->order)
        <a href="{{ route('purchase_orders.show', $purchase_receipt->order) }}"
           class="inline-flex items-center gap-1 px-3 py-1.5 text-xs rounded border border-sky-600 text-sky-300 hover:bg-sky-900/40">
          🔎 Ver OC {{ $purchase_receipt->order->order_number }}
        </a>
      @endif

      {{-- Acciones según estado --}}
      @if($purchase_receipt->status === 'pendiente_aprobacion')
        {{-- Aprobar --}}
        <x-confirm-button
          :action="route('purchase_receipts.approve', $purchase_receipt)"
          method="POST"
          question="¿Aprobar esta recepción y actualizar el stock?"
          text="Aprobar"
          icon="✅"
          color="emerald"
        />
        {{-- Rechazar --}}
        <x-confirm-button
          :action="route('purchase_receipts.reject', $purchase_receipt)"
          method="POST"
          question="¿Rechazar esta recepción? No afectará el stock."
          text="Rechazar"
          icon="❌"
          color="red"
        />
      @elseif($purchase_receipt->status === 'aprobado')
        {{-- 🧾 Imprimir recepción (ticket) --}}
        <a href="{{ route('purchase_receipts.ticket', $purchase_receipt) }}"
           target="_blank"
           class="inline-flex items-center gap-2 px-3 py-2 rounded-md bg-gray-800 hover:bg-gray-700 text-white text-sm">
          🧾 Imprimir recepción
        </a>

        {{-- Sello de auditoría --}}
        <span class="ml-2 text-xs px-2 py-1 rounded border border-emerald-700/50 bg-emerald-900/30 text-emerald-200">
          Aprobado
          @if($purchase_receipt->approved_at)
            el {{ \Illuminate\Support\Carbon::parse($purchase_receipt->approved_at)->format('d/m/Y H:i') }}
          @endif
          @if(optional($purchase_receipt->approvedBy)->name)
            por {{ $purchase_receipt->approvedBy->name }}
          @endif
        </span>
      @endif

    </x-action-buttons>
  </div> {{-- /encabezado --}}

  {{-- Flash --}}
  @if (session('success'))
    <div class="mb-4 rounded-lg border border-emerald-700/50 bg-emerald-900/30 text-emerald-200 px-4 py-3">
      {{ session('success') }}
    </div>
  @endif

  @if (session('error'))
    <div class="mb-4 rounded-lg border border-red-700/50 bg-red-900/30 text-red-200 px-4 py-3">
      {{ session('error') }}
    </div>
  @endif

  {{-- Resumen --}}
  <div class="bg-gray-900 border border-gray-700 rounded-xl p-5 mb-8">
    <div class="grid md:grid-cols-3 gap-4 text-sm">
      <div>
        <div class="text-gray-400 text-xs uppercase">Orden de compra</div>
        <div class="font-semibold">
          {{ $purchase_receipt->order?->order_number ?? '—' }}
        </div>
      </div>
      <div>
        <div class="text-gray-400 text-xs uppercase">Proveedor</div>
        <div class="font-semibold">
          {{ $purchase_receipt->order?->supplier?->name ?? '—' }}
        </div>
      </div>
      <div>
        <div class="text-gray-400 text-xs uppercase">Fecha de recepción</div>
        <div class="font-semibold">
          {{ \Illuminate\Support\Carbon::parse($purchase_receipt->received_date)->format('d/m/Y') }}
        </div>
      </div>
      <div>
        <div class="text-gray-400 text-xs uppercase">N° de recepción</div>
        <div class="font-mono">
          {{ $purchase_receipt->receipt_number }}
        </div>
      </div>
      <div>
        <div class="text-gray-400 text-xs uppercase">Recibido por</div>
        <div class="font-semibold">
          {{ optional($purchase_receipt->receivedBy)->name ?? ('Usuario #'.$purchase_receipt->received_by) }}
        </div>
      </div>
      <div>
        <div class="text-gray-400 text-xs uppercase">Creación / Actualización</div>
        <div class="font-semibold">
          {{ $purchase_receipt->created_at?->format('d/m/Y H:i') }} ·
          {{ $purchase_receipt->updated_at?->format('d/m/Y H:i') }}
        </div>
      </div>
    </div>
  </div>

  {{-- Ítems --}}
  <h2 class="font-semibold text-green-300 mb-3">Ítems recibidos</h2>
  <div class="bg-gray-900 border border-gray-700 rounded-xl p-0 overflow-hidden">
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-800 text-gray-300 uppercase tracking-wide">
          <tr>
            <th class="text-left p-3">Producto</th>
            <th class="text-right p-3">Pedida</th>
            <th class="text-right p-3">Recibida</th>
            <th class="text-right p-3">Costo</th>
            <th class="text-right p-3">Subtotal</th>
            <th class="text-left p-3">Estado</th>
          </tr>
        </thead>

        <tbody class="divide-y divide-gray-800 text-gray-100">
          @forelse($purchase_receipt->items as $it)
            <tr class="hover:bg-gray-800/50">
              <td class="p-3">{{ $it->product?->name ?? '—' }}</td>
              <td class="p-3 text-right">
                {{ number_format((int) $it->ordered_qty, 0, ',', '.') }}
              </td>
              <td class="p-3 text-right">
                {{ number_format((int) $it->received_qty, 0, ',', '.') }}
              </td>
              <td class="p-3 text-right">
                ₲ {{ number_format((float) $it->unit_cost, 0, ',', '.') }}
              </td>
              <td class="p-3 text-right">
                ₲ {{ number_format((float) $it->subtotal, 0, ',', '.') }}
              </td>
              <td class="p-3">
                <span class="px-2 py-0.5 rounded text-xs font-semibold
                  @class([
                    'bg-emerald-900/40 text-emerald-300 border border-emerald-700/50' => $it->status === 'completo',
                    'bg-yellow-900/40 text-yellow-300 border border-yellow-700/50'   => $it->status === 'parcial',
                    'bg-red-900/40 text-red-300 border border-red-700/50'           => $it->status === 'faltante',
                  ])">
                  {{ ucfirst($it->status) }}
                </span>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="p-6 text-center text-gray-400">
                No se encontraron ítems en esta recepción.
              </td>
            </tr>
          @endforelse
        </tbody>

        {{-- Totales --}}
        @php
          $totalQty = (int) $purchase_receipt->items->sum('received_qty');
          $totalVal = (float) $purchase_receipt->items->sum('subtotal');
        @endphp
        <tfoot class="bg-gray-800/60 text-gray-100">
          <tr>
            <td class="p-3 font-semibold text-right" colspan="2">Totales</td>
            <td class="p-3 text-right font-semibold">
              {{ number_format($totalQty, 0, ',', '.') }}
            </td>
            <td class="p-3"></td>
            <td class="p-3 text-right font-semibold">
              ₲ {{ number_format($totalVal, 0, ',', '.') }}
            </td>
            <td class="p-3"></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>

  {{-- Ayuda cuando está pendiente --}}
  @if($purchase_receipt->status === 'pendiente_aprobacion')
    <div class="mt-6 rounded-lg border border-amber-700/50 bg-amber-900/20 text-amber-200 px-4 py-3 text-sm">
      <strong>Nota:</strong> el stock se actualiza sólo al <span class="font-semibold">aprobar</span> la recepción.
    </div>
  @endif

</div>
@endsection
                    