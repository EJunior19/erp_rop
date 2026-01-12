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
                required
                onchange="if(this.value){ window.location='{{ route('purchase_receipts.create') }}?order='+this.value }">
          <option value="">Seleccione…</option>
          @foreach($orders as $o)
            <option value="{{ $o->id }}" @selected(optional($order)->id === $o->id)>
              {{ $o->order_number }} — {{ optional($o->supplier)->name ?? 'Sin proveedor' }}
            </option>
          @endforeach
        </select>

        {{-- Mantener q si venís filtrando --}}
        @if(!empty($q))
          <input type="hidden" name="q" value="{{ $q }}">
        @endif
      </div>

      <div>
        <label class="text-sm">Remito / Guía</label>
        <input name="receipt_number"
               value="{{ old('receipt_number') }}"
               class="w-full bg-gray-800 border border-gray-700 rounded p-2"
               required>
      </div>

      <div>
        <label class="text-sm">Fecha recepción</label>
        <input type="text"
               name="received_date_display"
               class="date-input w-full bg-gray-800 border border-gray-700 rounded p-2"
               placeholder="dd/mm/yy"
               value="{{ old('received_date_display') }}"
               required>
        <input type="hidden" name="received_date" value="{{ old('received_date') }}">
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

      <p class="text-xs text-gray-400 mt-2">
        Tip: Elegí una Orden de Compra y se cargan automáticamente sus ítems. Podés ajustar la cantidad recibida y el costo.
      </p>
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
    value = String(value ?? '').replace(/\D/g, "");
    if (!value) return "";
    return parseInt(value, 10).toLocaleString("es-PY");
}
function unformatMoney(value) {
    return String(value ?? '').replace(/\./g, "");
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

    // Validación básica
    if (dd.length < 1 || mm.length < 1 || yy.length < 4) return "";

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
   ➕ AGREGAR FILAS (soporta precarga desde $items)
========================================================= --}}
<script>
/**
 * rowsFromServer viene precargado desde PHP:
 * - Si viene ?order=ID, $items trae los productos/qty de la OC
 * - Si no, queda vacío y arrancamos con 1 fila
 */
const rowsFromServer = @json(($items ?? collect())->values());

function rowTemplate(idx, prefill = {}) {
  const pid     = prefill.product_id ?? '';
  const ordered = prefill.ordered_qty ?? '';
  const received= prefill.received_qty ?? '';
  const cost    = prefill.unit_cost ?? '';

  return `
  <div class="grid md:grid-cols-4 gap-2 border border-gray-700 rounded p-2">
    <div>
      <select name="items[${idx}][product_id]"
              class="w-full bg-gray-800 border border-gray-700 rounded p-2"
              required>
        <option value="">Producto…</option>
        @foreach($products as $p)
          <option value="{{ $p->id }}" ${String(pid)==='{{ $p->id }}' ? 'selected' : ''}>
            {{ $p->name }}
          </option>
        @endforeach
      </select>
    </div>

    <div>
      <input type="number"
             min="0"
             name="items[${idx}][ordered_qty]"
             class="w-full bg-gray-800 border border-gray-700 rounded p-2"
             placeholder="Pedida"
             value="${ordered}">
    </div>

    <div>
      <input type="number"
             min="0"
             name="items[${idx}][received_qty]"
             class="w-full bg-gray-800 border border-gray-700 rounded p-2"
             placeholder="Recibida"
             value="${received}">
    </div>

    <div>
      <input type="text"
             name="items[${idx}][unit_cost]"
             class="precio-input w-full bg-gray-800 border border-gray-700 rounded p-2"
             placeholder="Costo"
             value="${cost}">
    </div>
  </div>`;
}

let idx = 0;

function addRow(prefill = {}) {
  document.getElementById('items')
    .insertAdjacentHTML('beforeend', rowTemplate(idx, prefill));
  idx++;
}

(function bootRows(){
  if (rowsFromServer && rowsFromServer.length) {
    rowsFromServer.forEach(r => addRow(r));
  } else {
    addRow(); // 1 fila por defecto
  }

  // Formatear costos precargados (si vinieron)
  document.querySelectorAll('.precio-input').forEach(inp => {
    inp.value = formatMoney(inp.value);
  });
})();
</script>

@endsection
