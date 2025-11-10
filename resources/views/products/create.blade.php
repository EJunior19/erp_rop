@extends('layout.admin')
@section('content')

<h1 class="text-2xl font-semibold text-gray-800 mb-4">➕ Nuevo producto</h1>

{{-- 🔹 Muestra errores de validación --}}
@if ($errors->any())
  <div class="mb-4 p-3 bg-red-100 text-red-700 rounded">
    <ul class="list-disc ml-5">
      @foreach ($errors->all() as $e)
        <li>{{ $e }}</li>
      @endforeach
    </ul>
  </div>
@endif

<form method="POST" action="{{ route('products.store') }}"
      enctype="multipart/form-data"
      class="bg-gray-900 text-white rounded shadow p-4 space-y-4">
  @csrf

  {{-- 🔹 Nombre --}}
  <div>
    <label class="block mb-1 font-medium">Nombre</label>
    <input type="text" name="name" value="{{ old('name') }}"
           class="w-full px-3 py-2 rounded bg-gray-800 border border-gray-700 focus:ring focus:ring-blue-500" required>
  </div>

  {{-- 🔹 Marca --}}
  <div>
    <label class="block mb-1 font-medium">Marca</label>
    <select name="brand_id" class="w-full px-3 py-2 rounded bg-gray-800 border border-gray-700 focus:ring focus:ring-blue-500" required>
      <option value="">— Seleccioná —</option>
      @foreach($brands as $b)
        <option value="{{ $b->id }}" {{ old('brand_id') == $b->id ? 'selected' : '' }}>
          {{ $b->name }}
        </option>
      @endforeach
    </select>
  </div>

  {{-- 🔹 Categoría --}}
  <div>
    <label class="block mb-1 font-medium">Categoría</label>
    <select name="category_id" class="w-full px-3 py-2 rounded bg-gray-800 border border-gray-700 focus:ring focus:ring-blue-500" required>
      <option value="">— Seleccioná —</option>
      @foreach($categories as $c)
        <option value="{{ $c->id }}" {{ old('category_id') == $c->id ? 'selected' : '' }}>
          {{ $c->name }}
        </option>
      @endforeach
    </select>
  </div>

  {{-- 🔹 Proveedor --}}
  <div>
    <label class="block mb-1 font-medium">Proveedor</label>
    <select name="supplier_id" class="w-full px-3 py-2 rounded bg-gray-800 border border-gray-700 focus:ring focus:ring-blue-500" required>
      <option value="">— Seleccioná —</option>
      @foreach($suppliers as $s)
        <option value="{{ $s->id }}" {{ old('supplier_id') == $s->id ? 'selected' : '' }}>
          {{ $s->name }}
        </option>
      @endforeach
    </select>
  </div>

  {{-- 🔹 Precio contado (con máscara visual de miles) --}}
  <div>
    <label class="block mb-1 font-medium">Precio contado (Gs.)</label>
    <input type="text" inputmode="numeric" name="price_cash"
           value="{{ old('price_cash') }}"
           class="money-py w-full px-3 py-2 rounded bg-gray-800 border border-gray-700"
           placeholder="1.500.000">
  </div>

  {{-- 🔹 Precios en cuotas dinámicos --}}
  <div>
    <label class="block mb-1 font-medium">Precios en cuotas (opcional)</label>
    <div id="installments-wrapper" class="space-y-2">
      {{-- Si se había enviado algo y falló validación, se vuelve a mostrar --}}
      @if(old('installments'))
        @foreach(old('installments') as $i => $cuota)
          <div class="flex gap-2 installment-row">
            <input type="number" min="1" name="installments[{{ $i }}]" value="{{ old('installments.'.$i) }}"
                   placeholder="N° de cuotas"
                   class="w-1/3 px-3 py-2 rounded bg-gray-800 border border-gray-700">
            <input type="text" inputmode="numeric" name="installment_prices[{{ $i }}]" value="{{ old('installment_prices.'.$i) }}"
                   placeholder="Precio por cuota"
                   class="money-py w-2/3 px-3 py-2 rounded bg-gray-800 border border-gray-700">
            <button type="button" class="remove-installment px-2 bg-red-600 text-white rounded">✖</button>
          </div>
        @endforeach
      @endif
    </div>
    <button type="button" id="add-installment"
            class="mt-2 px-3 py-1 bg-purple-600 text-white rounded hover:bg-purple-700">
      ➕ Agregar cuota
    </button>
  </div>

  {{-- 🔹 Imágenes (múltiples + elegir portada) --}}
  <div>
    <label class="block mb-1 font-medium">Fotos del producto</label>
    <input type="file" name="images[]" multiple accept="image/*"
           class="w-full px-3 py-2 rounded bg-gray-800 border border-gray-700">
    <p class="text-sm text-gray-400 mt-1">
      Podés seleccionar varias. Elegí abajo cuál será la <strong>portada</strong>. Máx 4MB c/u.
    </p>

    {{-- índice de la portada entre los nuevos archivos --}}
    <input type="hidden" name="cover_index" id="cover_index" value="0">

    {{-- Previsualización con radios para portada --}}
    <div id="preview" class="mt-3 grid grid-cols-2 md:grid-cols-4 gap-3"></div>
  </div>

  {{-- 🔹 Activo --}}
  <div class="flex items-center">
    <input type="hidden" name="active" value="0">
    <input type="checkbox" name="active" id="active" value="1"
           class="w-4 h-4 text-blue-600 border-gray-600 rounded focus:ring-blue-500"
           {{ old('active', true) ? 'checked' : '' }}>
    <label for="active" class="ml-2">Activo</label>
  </div>

  {{-- 🔹 Notas --}}
  <div>
    <label class="block mb-1 font-medium">Notas</label>
    <textarea name="notes" rows="3"
              class="w-full px-3 py-2 rounded bg-gray-800 border border-gray-700">{{ old('notes') }}</textarea>
  </div>

  {{-- 🔹 Acciones --}}
  <div class="flex gap-2 mt-4">
    <button class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">Guardar</button>
    <a href="{{ route('products.index') }}"
       class="px-4 py-2 border border-gray-400 text-gray-400 rounded hover:bg-gray-500 hover:text-white">
       Cancelar
    </a>
  </div>
</form>

{{-- 🔹 Scripts: cuotas dinámicas + máscara de miles + preview imágenes con portada --}}
@push('scripts')
<script>
  // Máscara visual para inputs monetarios (puntos de miles). El backend limpia.
  document.addEventListener('input', function(e){
    if(!e.target.matches('.money-py')) return;
    const el = e.target;
    let raw = el.value.replace(/\D+/g,'');
    el.value = raw.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  });

  // Agregar filas con índices consistentes
  document.getElementById('add-installment').addEventListener('click', function() {
    const wrapper = document.getElementById('installments-wrapper');
    const idx = wrapper.querySelectorAll('.installment-row').length;

    const div = document.createElement('div');
    div.classList.add('flex','gap-2','mt-1','installment-row');
    div.innerHTML = `
        <input type="number" min="1" name="installments[${idx}]" placeholder="N° de cuotas"
               class="w-1/3 px-3 py-2 rounded bg-gray-800 border border-gray-700">
        <input type="text" inputmode="numeric" name="installment_prices[${idx}]" placeholder="Precio por cuota"
               class="money-py w-2/3 px-3 py-2 rounded bg-gray-800 border border-gray-700">
        <button type="button" class="remove-installment px-2 bg-red-600 text-white rounded">✖</button>
    `;
    wrapper.appendChild(div);
  });

  // Eliminar filas
  document.getElementById('installments-wrapper').addEventListener('click', function(e) {
    if (e.target.classList.contains('remove-installment')) {
        e.target.closest('.installment-row').remove();
    }
  });

  // Preview de imágenes seleccionadas + elegir portada (cover_index)
  (function(){
    const fileInput = document.querySelector('input[name="images[]"]');
    const preview   = document.getElementById('preview');
    const coverIdx  = document.getElementById('cover_index');

    if (!fileInput || !preview || !coverIdx) return;

    fileInput.addEventListener('change', () => {
      preview.innerHTML = '';
      const files = Array.from(fileInput.files || []);
      files.forEach((file, idx) => {
        if (!file.type.startsWith('image/')) return;
        const reader = new FileReader();
        reader.onload = (e) => {
          const isDefault = idx === 0;
          const cont = document.createElement('label');
          cont.className = 'relative rounded border border-gray-700 p-2 block cursor-pointer';
          cont.innerHTML = `
            <img src="${e.target.result}" class="w-full h-32 object-cover rounded">
            <div class="flex items-center justify-between mt-2 text-xs text-gray-300">
              <span class="truncate pr-2">${file.name}</span>
              <span class="inline-flex items-center gap-1">
                <input type="radio" name="cover_index_radio" value="${idx}" ${isDefault ? 'checked' : ''}>
                Portada
              </span>
            </div>
          `;
          preview.appendChild(cont);
          if (isDefault) coverIdx.value = '0';
        };
        reader.readAsDataURL(file);
      });
    });

    // Mantener sincronizado el hidden cover_index
    document.addEventListener('change', (e) => {
      if (e.target && e.target.name === 'cover_index_radio') {
        coverIdx.value = String(e.target.value || '0');
      }
    });
  })();
</script>
@endpush

@endsection
