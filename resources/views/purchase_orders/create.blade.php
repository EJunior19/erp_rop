@extends('layout.admin')
@section('content')

<div class="p-6">
  <h1 class="text-2xl font-bold text-green-400 mb-4">➕ Nueva Orden de Compra</h1>

  <form method="POST"
        action="{{ route('purchase_orders.store') }}"
        id="purchaseOrderForm"
        class="bg-gray-900 border border-gray-700 rounded p-6 space-y-4 text-gray-200">
    @csrf

    {{-- ================= CABECERA ================= --}}
    <div class="grid md:grid-cols-3 gap-4">
      <div>
        <label class="text-sm">Proveedor</label>
        <select name="supplier_id"
                class="w-full bg-gray-800 border border-gray-700 rounded p-2"
                required>
          <option value="">Seleccione…</option>
          @foreach($suppliers as $s)
            <option value="{{ $s->id }}">{{ $s->name }}</option>
          @endforeach
        </select>
      </div>

      <div>
        <label class="text-sm">Fecha OC</label>
        <input type="text"
               name="order_date_display"
               class="date-input w-full bg-gray-800 border border-gray-700 rounded p-2"
               placeholder="dd/mm/yy"
               maxlength="8"
               required>
        <input type="hidden" name="order_date">
      </div>

      <div>
        <label class="text-sm">Fecha esperada</label>
        <input type="text"
               name="expected_date_display"
               class="date-input w-full bg-gray-800 border border-gray-700 rounded p-2"
               placeholder="dd/mm/yy"
               maxlength="8">
        <input type="hidden" name="expected_date">
      </div>
    </div>

    {{-- ================= ITEMS ================= --}}
    <div class="mt-4">
      <h2 class="font-semibold text-green-300 mb-2">Ítems</h2>
      <div id="items" class="space-y-2"></div>

      <button type="button"
              onclick="addRow()"
              class="mt-2 px-3 py-1 border border-gray-600 rounded hover:bg-gray-800">
        + Agregar ítem
      </button>
    </div>

    {{-- ================= NOTAS ================= --}}
    <div>
      <label class="text-sm">Notas</label>
      <textarea name="notes"
                rows="3"
                class="w-full bg-gray-800 border border-gray-700 rounded p-2"></textarea>
    </div>

    <button class="px-4 py-2 bg-green-600 rounded hover:bg-green-700">
      Guardar
    </button>
  </form>
</div>

{{-- =========================================================
   💰 FORMATEO DE PRECIOS (Gs sin decimales)
========================================================= --}}
<script>
function formatMoney(value) {
    value = value.replace(/\D/g, "");
    if (!value) return "";
    return parseInt(value, 10).toLocaleString("es-PY");
}

function unformatMoney(value) {
    return value.replace(/\./g, "");
}

document.addEventListener("input", function (e) {
    if (e.target.classList.contains("precio-input")) {
        e.target.value = formatMoney(e.target.value);
    }
});

document.addEventListener("submit", function () {
    document.querySelectorAll(".precio-input").forEach(input => {
        input.value = unformatMoney(input.value);
    });
});
</script>

{{-- =========================================================
   📅 FECHAS dd/mm/yy con / automático → yyyy-mm-dd
========================================================= --}}
<script>
document.querySelectorAll(".date-input").forEach(input => {
    input.addEventListener("input", e => {
        let v = e.target.value.replace(/\D/g, "");

        if (v.length > 6) v = v.slice(0, 6);

        if (v.length >= 5) {
            e.target.value = `${v.slice(0,2)}/${v.slice(2,4)}/${v.slice(4,6)}`;
        } else if (v.length >= 3) {
            e.target.value = `${v.slice(0,2)}/${v.slice(2,4)}`;
        } else {
            e.target.value = v;
        }
    });
});

function toISO(value) {
    if (!value || value.length !== 8) return "";
    const [dd, mm, yy] = value.split("/");
    return `20${yy}-${mm}-${dd}`;
}

document.getElementById("purchaseOrderForm").addEventListener("submit", () => {
    document.querySelector("[name='order_date']")
        .value = toISO(document.querySelector("[name='order_date_display']").value);

    document.querySelector("[name='expected_date']")
        .value = toISO(document.querySelector("[name='expected_date_display']").value);
});
</script>

{{-- =========================================================
   ➕ AGREGAR FILAS
========================================================= --}}
<script>
function rowTemplate(idx) {
  return `
  <div class="grid md:grid-cols-3 gap-2 border border-gray-700 rounded p-2">
    <div>
      <select name="items[${idx}][product_id]"
              class="w-full bg-gray-800 border border-gray-700 rounded p-2"
              required>
        <option value="">Producto…</option>
        @foreach($products as $p)
          <option value="{{ $p->id }}">{{ $p->name }}</option>
        @endforeach
      </select>
    </div>

    <div>
      <input type="number"
             min="1"
             name="items[${idx}][quantity]"
             class="w-full bg-gray-800 border border-gray-700 rounded p-2"
             placeholder="Cantidad"
             required>
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
