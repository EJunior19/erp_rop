@extends('layout.admin')
@section('content')

<div class="p-6">
  <h1 class="text-2xl font-bold text-green-400 mb-4">🚛 Registrar Recepción</h1>

  <form method="POST"
        action="{{ route('purchase_receipts.store') }}"
        id="purchaseReceiptForm"
        class="bg-gray-900 border border-gray-700 rounded p-6 space-y-4 text-gray-200">
    @csrf

    {{-- ================= CABECERA ================= --}}
    <div class="grid md:grid-cols-3 gap-4">
      <div>
        <label class="text-sm">Orden de compra</label>
        <select name="purchase_order_id"
                class="w-full bg-gray-800 border border-gray-700 rounded p-2"
                required>
          <option value="">Seleccione…</option>
          @foreach($orders as $o)
            <option value="{{ $o->id }}">
              {{ $o->order_number }} — {{ $o->supplier->name }}
            </option>
          @endforeach
        </select>
      </div>

      <div>
        <label class="text-sm">Remito / Guía</label>
        <input name="receipt_number"
               class="w-full bg-gray-800 border border-gray-700 rounded p-2"
               required>
      </div>

      <div>
        <label class="text-sm">Fecha recepción</label>
        <input type="text"
               name="received_date_display"
               class="date-input w-full bg-gray-800 border border-gray-700 rounded p-2"
               placeholder="dd/mm/yy"
               required>
        <input type="hidden" name="received_date">
      </div>
    </div>

    {{-- ================= ITEMS ================= --}}
    <div class="mt-4">
      <h2 class="font-semibold text-green-300 mb-2">Ítems recibidos</h2>
      <div id="items" class="space-y-2"></div>

      <button type="button"
              onclick="addRow()"
              class="mt-2 px-3 py-1 border border-gray-600 rounded hover:bg-gray-800">
        + Agregar ítem
      </button>
    </div>

    {{-- ================= NOTAS ================= --}}
    <div>
      <label class="block text-sm font-semibold text-green-300 mb-1">
        Notas (opcional)
      </label>
      <textarea name="notes"
                rows="3"
                class="w-full rounded-lg border-gray-700 bg-gray-800 text-gray-100 px-3 py-2">{{ old('notes') }}</textarea>
    </div>

    <button class="px-4 py-2 bg-green-600 rounded hover:bg-green-700">
      Guardar
    </button>
  </form>
</div>

{{-- =========================================================
   💰 FORMATEO DE COSTOS (Gs sin decimales)
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
   📅 FECHAS dd/mm/yy → yyyy-mm-dd (con / automático)
========================================================= --}}
<script>
function autoSlashDate(input) {
    let v = input.value.replace(/\D/g, "");
    if (v.length >= 3) v = v.slice(0,2) + "/" + v.slice(2);
    if (v.length >= 6) v = v.slice(0,5) + "/" + v.slice(5,9);
    input.value = v;
}

function parseDateToISO(value) {
    if (!value) return "";
    const parts = value.split("/");
    if (parts.length !== 3) return "";

    let [dd, mm, yy] = parts;
    if (yy.length === 2) yy = "20" + yy;

    return `${yy}-${mm.padStart(2,"0")}-${dd.padStart(2,"0")}`;
}

document.querySelector('[name="received_date_display"]')
  .addEventListener("input", function () {
      autoSlashDate(this);
  });

document.getElementById("purchaseReceiptForm")
  .addEventListener("submit", function () {
      const display = document.querySelector('[name="received_date_display"]').value;
      document.querySelector('[name="received_date"]').value = parseDateToISO(display);
  });
</script>

{{-- =========================================================
   ➕ AGREGAR FILAS
========================================================= --}}
<script>
function rowTemplate(idx) {
  return `
  <div class="grid md:grid-cols-4 gap-2 border border-gray-700 rounded p-2">

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
             min="0"
             name="items[${idx}][ordered_qty]"
             class="w-full bg-gray-800 border border-gray-700 rounded p-2"
             placeholder="Pedida">
    </div>

    <div>
      <input type="number"
             min="0"
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
    document.getElementById('items')
        .insertAdjacentHTML('beforeend', rowTemplate(idx++));
}
addRow();
</script>

@endsection
