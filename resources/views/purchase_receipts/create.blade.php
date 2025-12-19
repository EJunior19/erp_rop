@extends('layout.admin')
@section('content')
<div class="p-6">
  <h1 class="text-2xl font-bold text-green-400 mb-4">🚛 Registrar Recepción</h1>

  <form method="POST" action="{{ route('purchase_receipts.store') }}" class="bg-gray-900 border border-gray-700 rounded p-6 space-y-4 text-gray-200">
    @csrf

    <div class="grid md:grid-cols-3 gap-4">
      <div>
        <label class="text-sm">Orden de compra</label>
        <select name="purchase_order_id" class="w-full bg-gray-800 border border-gray-700 rounded p-2" required>
          <option value="">Seleccione…</option>
          @foreach($orders as $o)
            <option value="{{ $o->id }}">{{ $o->order_number }} — {{ $o->supplier->name }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="text-sm">Remito/Guía</label>
        <input name="receipt_number" class="w-full bg-gray-800 border border-gray-700 rounded p-2" required>
      </div>
      <div>
        <label class="text-sm">Fecha recepción</label>
        <input type="date" name="received_date" class="w-full bg-gray-800 border border-gray-700 rounded p-2" required>
      </div>
    </div>

    <div class="mt-4">
      <h2 class="font-semibold text-green-300 mb-2">Ítems recibidos</h2>
      <div id="items" class="space-y-2"></div>
      <button type="button" onclick="addRow()" class="mt-2 px-3 py-1 border rounded">+ Agregar ítem</button>
    </div>

    <div class="md:col-span-2">
      <label class="block text-sm font-semibold text-green-300 mb-1">Notas (opcional)</label>
      <textarea name="notes" rows="3"
        class="w-full rounded-lg border-gray-700 bg-gray-800 text-gray-100 px-3 py-2">{{ old('notes') }}</textarea>
    </div>

    <button class="px-4 py-2 bg-green-600 rounded">Guardar</button>
  </form>
</div>

<script>
/* -------------------------------------
   💰 FORMATEO SIN DECIMALES (Gs)
-------------------------------------- */

// Convierte 100000 → 100.000
function formatMoney(value) {
    value = value.replace(/\./g, "").replace(/,/g, "");

    if (isNaN(value) || value === "") return "";

    let number = parseInt(value);
    return number.toLocaleString("es-ES");
}

// Devuelve solo número limpio → 100000
function unformatMoney(value) {
    return value.replace(/\./g, "");
}

// Formatea mientras el usuario escribe
document.addEventListener("input", function (e) {
    if (e.target.classList.contains("precio-input")) {
        let raw = e.target.value;
        e.target.value = formatMoney(raw);
    }
});

// Quita formato antes de enviar
document.addEventListener("submit", function () {
    document.querySelectorAll(".precio-input").forEach(function (input) {
        input.value = unformatMoney(input.value);
    });
});

/* -------------------------------------
   ➕ GENERAR FILAS
-------------------------------------- */
function rowTemplate(idx) {
  return `
  <div class="grid md:grid-cols-4 gap-2 border border-gray-700 rounded p-2">

    <div>
      <select name="items[${idx}][product_id]" class="w-full bg-gray-800 border border-gray-700 rounded p-2" required>
        <option value="">Producto…</option>
        @foreach($products as $p)
        <option value="{{ $p->id }}">{{ $p->name }}</option>
        @endforeach
      </select>
    </div>

    <div>
      <input type="number" min="0" 
             name="items[${idx}][ordered_qty]" 
             class="w-full bg-gray-800 border border-gray-700 rounded p-2"
             placeholder="Pedida">
    </div>

    <div>
      <input type="number" min="0" 
             name="items[${idx}][received_qty]" 
             class="w-full bg-gray-800 border border-gray-700 rounded p-2"
             placeholder="Recibida">
    </div>

    <div>
      <input type="text" 
             name="items[${idx}][unit_cost]" 
             class="precio-input w-full bg-gray-800 border border-gray-700 rounded p-2"
             placeholder="Costo">
    </div>

  </div>`;
}

let idx = 0;
function addRow() {
    document.getElementById('items').insertAdjacentHTML('beforeend', rowTemplate(idx++));
}
addRow();
</script>

@endsection
