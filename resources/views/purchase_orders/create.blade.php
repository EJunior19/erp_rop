@extends('layout.admin')
@section('content')
<div class="p-6">
  <h1 class="text-2xl font-bold text-green-400 mb-4">➕ Nueva Orden de Compra</h1>

  <form method="POST" action="{{ route('purchase_orders.store') }}" class="bg-gray-900 border border-gray-700 rounded p-6 space-y-4 text-gray-200">
    @csrf

    <div class="grid md:grid-cols-3 gap-4">
      <div>
        <label class="text-sm">Proveedor</label>
        <select name="supplier_id" class="w-full bg-gray-800 border border-gray-700 rounded p-2" required>
          <option value="">Seleccione…</option>
          @foreach($suppliers as $s)
            <option value="{{ $s->id }}">{{ $s->name }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="text-sm">Fecha OC</label>
        <input type="date" name="order_date" class="w-full bg-gray-800 border border-gray-700 rounded p-2" required>
      </div>
      <div>
        <label class="text-sm">Fecha esperada</label>
        <input type="date" name="expected_date" class="w-full bg-gray-800 border border-gray-700 rounded p-2">
      </div>
    </div>

    <div class="mt-4">
      <h2 class="font-semibold text-green-300 mb-2">Ítems</h2>
      <template x-data x-if="false"></template>
      <div id="items" class="space-y-2"></div>
      <button type="button" onclick="addRow()" class="mt-2 px-3 py-1 border rounded">+ Agregar ítem</button>
    </div>

    <div>
      <label class="text-sm">Notas</label>
      <textarea name="notes" rows="3" class="w-full bg-gray-800 border border-gray-700 rounded p-2"></textarea>
    </div>

    <button class="px-4 py-2 bg-green-600 rounded">Guardar</button>
  </form>
</div>

<script>
/* -------------------------------------
   💰 FORMATEO SIN DECIMALES (Gs)
-------------------------------------- */

// Formatea: 100000 → 100.000
function formatMoney(value) {
    // quitar puntos y comas previos
    value = value.replace(/\./g, "").replace(/,/g, "");

    if (isNaN(value) || value === "") return "";

    let number = parseInt(value);

    return number.toLocaleString("es-ES");
}

// Quitar formato antes de enviar
function unformatMoney(value) {
    return value.replace(/\./g, "");
}

// Formatear mientras se escribe
document.addEventListener("input", function (e) {
    if (e.target.classList.contains("precio-input")) {
        let raw = e.target.value;
        e.target.value = formatMoney(raw);
    }
});

// Antes de enviar, remover formato
document.addEventListener("submit", function () {
    document.querySelectorAll(".precio-input").forEach(function (input) {
        input.value = unformatMoney(input.value);
    });
});

/* -------------------------------------
   ➕ AGREGAR FILAS
-------------------------------------- */

function rowTemplate(idx) {
  return `
  <div class="grid md:grid-cols-3 gap-2 border border-gray-700 rounded p-2">
    <div>
      <select name="items[${idx}][product_id]" class="w-full bg-gray-800 border border-gray-700 rounded p-2" required>
        <option value="">Producto…</option>
        @foreach($products as $p)
        <option value="{{ $p->id }}">{{ $p->name }}</option>
        @endforeach
      </select>
    </div>

    <div>
      <input type="number" min="1" 
             name="items[${idx}][quantity]" 
             class="w-full bg-gray-800 border border-gray-700 rounded p-2" 
             placeholder="Cantidad" required>
    </div>

    <div>
      <input type="text" 
             name="items[${idx}][unit_price]" 
             class="precio-input w-full bg-gray-800 border border-gray-700 rounded p-2" 
             placeholder="Precio">
    </div>
  </div>`;
}

let idx = 0;
function addRow() {
    document.getElementById('items')
        .insertAdjacentHTML('beforeend', rowTemplate(idx++));
}
addRow();
</script>

@endsection
