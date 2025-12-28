@extends('layout.admin')

@section('content')
<div class="max-w-7xl mx-auto px-4 pt-2 pb-6 text-white"> {{-- antes: py-6 --}}

  <h1 class="text-2xl font-semibold mb-4 flex items-center gap-2">
    🧾 Nueva factura de compra
  </h1>

  {{-- Errores --}}
  @if ($errors->any())
    <div class="bg-red-100 text-red-800 border border-red-300 rounded px-3 py-2 mb-4 text-sm">
      <ul class="list-disc list-inside">
        @foreach ($errors->all() as $e)
          <li>{{ $e }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  @if(session('success'))
    <div class="bg-green-100 text-green-800 border border-green-300 rounded px-3 py-2 mb-4 text-sm">
      {{ session('success') }}
    </div>
  @endif

  <a href="{{ route('purchase_invoices.index') }}"
     class="inline-flex items-center text-sm text-gray-300 hover:text-white mb-4">
    ← Volver al listado
  </a>

  {{-- 1) Selector de recepción --}}
  <div class="bg-gray-800 rounded shadow p-4 mb-5">
    <h2 class="text-lg font-semibold mb-3 flex items-center gap-2">
      <span class="text-sky-400">📦</span> Seleccionar recepción aprobada
    </h2>

    <form method="GET" action="{{ route('purchase_invoices.create') }}" class="flex flex-col md:flex-row gap-3">
      <div class="flex-1">
        <select name="receipt"
                class="w-full rounded border border-gray-600 bg-gray-900 text-gray-100 text-sm px-3 py-2 focus:outline-none focus:ring focus:ring-indigo-500">
          <option value="">-- Elegí una recepción --</option>
          @foreach($receipts as $r)
            @php
              $order    = $r->order;
              $supplier = $order?->supplier;
            @endphp
            <option value="{{ $r->id }}"
              {{ (optional($selected)->id === $r->id) ? 'selected' : '' }}>
              RCP {{ $r->receipt_number }}
              — OC: {{ $order?->order_number ?? 'N/D' }}
              — Proveedor: {{ $supplier?->name ?? 'N/D' }}
              — Fecha: {{ \Illuminate\Support\Carbon::parse($r->received_date)->format('d/m/Y') }}
            </option>
          @endforeach
        </select>
      </div>
      <div>
        <button class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm rounded">
          Cargar recepción
        </button>
      </div>
    </form>

    @if(!$selected)
      <p class="text-xs text-gray-300 mt-3">
        Elegí una recepción aprobada para poder cargar la factura del proveedor y generar la cuenta por pagar.
      </p>
    @endif
  </div>

  @if($selected)
    @php
      $order    = $selected->order;
      $supplier = $order?->supplier;
    @endphp

    {{-- 2) Resumen proveedor / OC / recepción --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-5">
      <div class="bg-gray-800 rounded shadow p-4 text-sm">
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
        <div><span class="font-semibold">RCP:</span> {{ $selected->receipt_number }}</div>
        <div class="text-gray-300 text-xs">
          Recibida: {{ \Illuminate\Support\Carbon::parse($selected->received_date)->format('d/m/Y') }}
        </div>
        <div class="text-gray-300 text-xs">
          Estado: <span class="uppercase">{{ $selected->status }}</span>
        </div>
      </div>
    </div>

    {{-- 3) Formulario principal --}}
    <form method="POST" action="{{ route('purchase_invoices.store') }}" class="space-y-5">
      @csrf
      <input type="hidden" name="purchase_receipt_id" value="{{ $selected->id }}">

      {{-- Cabecera factura --}}
      <div class="bg-gray-800 rounded shadow p-4">
        <h3 class="text-sm font-semibold mb-3 text-emerald-400">
          Datos de la factura del proveedor
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-sm">
          <div>
            <label class="block text-xs mb-1">Nº factura proveedor</label>
            <input type="text"
                   name="invoice_number"
                   value="{{ old('invoice_number') }}"
                   class="w-full rounded border border-gray-600 bg-gray-900 text-gray-100 px-3 py-2 text-sm focus:outline-none focus:ring focus:ring-indigo-500"
                   required>
          </div>
            <div>
            <label class="block text-xs mb-1">Fecha de factura</label>
            <input type="date"
                    name="invoice_date"
                    value="{{ old('invoice_date', now()->toDateString()) }}"
                    class="w-full rounded border border-gray-600 bg-gray-900 text-gray-100 px-3 py-2 text-sm focus:outline-none focus:ring focus:ring-indigo-500"
                    required>
            </div>

          <div>
            <label class="block text-xs mb-1">Condición de pago</label>
            @php $pt = old('payment_term', 'contado'); @endphp
            <select name="payment_term"
                    class="w-full rounded border border-gray-600 bg-gray-900 text-gray-100 px-3 py-2 text-sm focus:outline-none focus:ring focus:ring-indigo-500"
                    required>
              <option value="contado"  {{ $pt === 'contado'  ? 'selected' : '' }}>Contado</option>
              <option value="credito"  {{ $pt === 'credito'  ? 'selected' : '' }}>Crédito</option>
              <option value="zafra"    {{ $pt === 'zafra'    ? 'selected' : '' }}>Zafra</option>
              <option value="especial" {{ $pt === 'especial' ? 'selected' : '' }}>Especial</option>
            </select>
          </div>

          <div>
            <label class="block text-xs mb-1">Vencimiento (si crédito)</label>
            <input type="date"
                   name="due_date"
                   value="{{ old('due_date') }}"
                   class="w-full rounded border border-gray-600 bg-gray-900 text-gray-100 px-3 py-2 text-sm focus:outline-none focus:ring focus:ring-indigo-500">
          </div>

          <div>
            <label class="block text-xs mb-1">Adelanto ya pagado (Gs.)</label>
            <input type="text"
                   name="advance_amount"
                   value="{{ old('advance_amount', '0') }}"
                   class="w-full rounded border border-gray-600 bg-gray-900 text-gray-100 px-3 py-2 text-sm focus:outline-none focus:ring focus:ring-indigo-500"
                   placeholder="0">
            <p class="text-[11px] text-gray-300 mt-1">
              Se descuenta del total para la cuenta por pagar.
            </p>
          </div>

          <div class="md:col-span-3">
            <label class="block text-xs mb-1">Notas / observación</label>
            <textarea name="notes"
                      rows="2"
                      class="w-full rounded border border-gray-600 bg-gray-900 text-gray-100 px-3 py-2 text-sm focus:outline-none focus:ring focus:ring-indigo-500"
                      placeholder="Detalle adicional, condiciones especiales, etc.">{{ old('notes') }}</textarea>
          </div>
        </div>
      </div>

      {{-- Ítems --}}
      <div class="bg-gray-800 rounded shadow">
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-700">
          <h3 class="text-sm font-semibold text-emerald-400">Ítems a facturar</h3>
          <span class="text-[11px] text-gray-300">
            Solo podés facturar hasta la cantidad recibida menos lo ya facturado.
          </span>
        </div>

        <div class="overflow-x-auto">
          <table class="min-w-full text-sm text-left">
            <thead class="bg-gray-900 text-gray-200 text-xs uppercase">
              <tr>
                <th class="px-3 py-2">#</th>
                <th class="px-3 py-2">Producto</th>
                <th class="px-3 py-2 text-right">Recibido</th>
                <th class="px-3 py-2 text-right">Ya facturado</th>
                <th class="px-3 py-2 text-right">Disponible</th>
                <th class="px-3 py-2 text-right">Cant. a facturar</th>
                <th class="px-3 py-2 text-right">Costo unit.</th>
                <th class="px-3 py-2 text-right">IVA %</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-700 bg-gray-900">
              @php $rowIndex = 0; @endphp
              @forelse($selected->items as $item)
                @php
                  $invoiced  = (int)($item->invoiced_qty ?? 0);
                  $remaining = max(0, (int)$item->received_qty - $invoiced);
                @endphp

                @if($remaining <= 0)
                  @continue
                @endif

                <tr>
                  <td class="px-3 py-2">{{ $rowIndex + 1 }}</td>
                  <td class="px-3 py-2">
                    <div class="font-semibold">
                      {{ $item->product?->name ?? '[Producto]' }}
                    </div>
                    <div class="text-xs text-gray-300">
                      Código: {{ $item->product?->code ?? 'N/D' }}
                    </div>
                  </td>
                  <td class="px-3 py-2 text-right">
                    {{ number_format($item->received_qty, 0, ',', '.') }}
                  </td>
                  <td class="px-3 py-2 text-right">
                    {{ number_format($invoiced, 0, ',', '.') }}
                  </td>
                  <td class="px-3 py-2 text-right text-sky-300">
                    {{ number_format($remaining, 0, ',', '.') }}
                  </td>
                  <td class="px-3 py-2 text-right">
                    <input type="number"
                           name="items[{{ $rowIndex }}][qty]"
                           value="{{ old("items.$rowIndex.qty", $remaining) }}"
                           min="1"
                           max="{{ $remaining }}"
                           class="w-24 text-right rounded border border-gray-600 bg-gray-900 text-gray-100 px-2 py-1 text-sm focus:outline-none focus:ring focus:ring-indigo-500">
                  </td>
                  <td class="px-3 py-2 text-right">
                    <div class="inline-block w-28">
                      <input type="text"
                            name="items[{{ $rowIndex }}][unit_cost]"
                            value="{{ old(
                                "items.$rowIndex.unit_cost",
                                isset($item->unit_cost) ? intval($item->unit_cost / 100) : 0
                            ) }}"
                            class="unit-cost w-full text-right tabular-nums rounded border border-gray-600 bg-gray-900 text-gray-100 px-2 py-1 text-sm focus:outline-none focus:ring focus:ring-indigo-500">
                    </div>
                  </td>
                  <td class="px-3 py-2 text-right">
                    <input type="number"
                           name="items[{{ $rowIndex }}][tax_rate]"
                           value="{{ old("items.$rowIndex.tax_rate", 10) }}"
                           min="0" max="100"
                           class="w-20 text-right rounded border border-gray-600 bg-gray-900 text-gray-100 px-2 py-1 text-sm focus:outline-none focus:ring focus:ring-indigo-500">
                  </td>
                </tr>

                {{-- hidden fields --}}
                <input type="hidden"
                       name="items[{{ $rowIndex }}][purchase_receipt_item_id]"
                       value="{{ $item->id }}">
                <input type="hidden"
                       name="items[{{ $rowIndex }}][product_id]"
                       value="{{ $item->product_id }}">

                @php $rowIndex++; @endphp
              @empty
                <tr>
                  <td colspan="8" class="px-3 py-4 text-center text-gray-300">
                    Esta recepción no tiene ítems para facturar.
                  </td>
                </tr>
              @endforelse

              @if($rowIndex === 0)
                <tr>
                  <td colspan="8" class="px-3 py-4 text-center text-yellow-300">
                    Todos los ítems de esta recepción ya fueron facturados.
                  </td>
                </tr>
              @endif
            </tbody>
          </table>
        </div>

        <div class="px-4 py-3 border-t border-gray-700 flex justify-end">
          <button type="submit"
                  class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm rounded">
            Guardar factura y crear cuenta por pagar
          </button>
        </div>
      </div>
    </form>
  @endif
</div>

{{-- 🔢 Formateo de costos y adelanto en tiempo real --}}
<script>
document.addEventListener("DOMContentLoaded", () => {

    const inputs = document.querySelectorAll(
        "input.unit-cost, input[name='advance_amount']"
    );

    const formatNumber = (value) => {
        value = value.replace(/\D/g, ""); // solo números
        if (!value) return "";
        return new Intl.NumberFormat("es-PY").format(value);
    };

    const cleanNumber = (value) => value.replace(/\./g, "");

    inputs.forEach(input => {

        // 👉 Formatear valor inicial (desde BD / old())
        if (input.value) {
            input.value = formatNumber(cleanNumber(input.value));
        }

        // 👉 Mientras escribe
        input.addEventListener("input", (e) => {
            const raw = cleanNumber(e.target.value);
            e.target.value = formatNumber(raw);
            e.target.setSelectionRange(
                e.target.value.length,
                e.target.value.length
            );
        });

        // 👉 Antes de enviar: SOLO quitar puntos
        input.form?.addEventListener("submit", () => {
            input.value = cleanNumber(input.value);
        });
    });

});
</script>
@endsection
