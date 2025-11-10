{{-- resources/views/products/edit.blade.php --}}
@extends('layout.admin')

@section('content')
<h1 class="text-2xl font-semibold text-gray-200 mb-4">✏️ Editar producto</h1>

{{-- 🔹 Errores de validación --}}
@if ($errors->any())
  <div class="mb-4 p-3 bg-red-100 text-red-700 rounded">
    <ul class="list-disc ml-5">
      @foreach ($errors->all() as $e)
        <li>{{ $e }}</li>
      @endforeach
    </ul>
  </div>
@endif

{{-- ========= FORM PRINCIPAL (único) ========= --}}
<form method="POST" action="{{ route('products.update',$product) }}"
      enctype="multipart/form-data"
      class="bg-gray-900 text-white rounded shadow p-4 space-y-4">
  @csrf @method('PUT')

  {{-- Código (solo lectura) --}}
  <div>
    <label class="block mb-1 font-medium">Código</label>
    <input type="text" value="{{ $product->code ?? '—' }}" disabled
           class="w-full px-3 py-2 rounded bg-gray-700 border border-gray-600 text-gray-300 font-mono">
  </div>

  {{-- Nombre --}}
  <div>
    <label class="block mb-1 font-medium">Nombre</label>
    <input type="text" name="name" value="{{ old('name',$product->name) }}" required
           class="w-full px-3 py-2 rounded bg-gray-800 border border-gray-700 focus:ring focus:ring-blue-500">
  </div>

  {{-- Marca --}}
  <div>
    <label class="block mb-1 font-medium">Marca</label>
    <select name="brand_id" required
            class="w-full px-3 py-2 rounded bg-gray-800 border border-gray-700 focus:ring focus:ring-blue-500">
      @foreach($brands as $b)
        <option value="{{ $b->id }}" @selected(old('brand_id',$product->brand_id)==$b->id)>
          {{ $b->name }}
        </option>
      @endforeach
    </select>
  </div>

  {{-- Categoría --}}
  <div>
    <label class="block mb-1 font-medium">Categoría</label>
    <select name="category_id" required
            class="w-full px-3 py-2 rounded bg-gray-800 border border-gray-700 focus:ring focus:ring-blue-500">
      @foreach($categories as $c)
        <option value="{{ $c->id }}" @selected(old('category_id',$product->category_id)==$c->id)>
          {{ $c->name }}
        </option>
      @endforeach
    </select>
  </div>

  {{-- Proveedor --}}
  <div>
    <label class="block mb-1 font-medium">Proveedor</label>
    <select name="supplier_id" required
            class="w-full px-3 py-2 rounded bg-gray-800 border border-gray-700 focus:ring focus:ring-blue-500">
      @foreach($suppliers as $s)
        <option value="{{ $s->id }}" @selected(old('supplier_id',$product->supplier_id)==$s->id)>
          {{ $s->name }}
        </option>
      @endforeach
    </select>
  </div>

  {{-- Precio contado (con máscara visual) --}}
  <div>
    <label class="block mb-1 font-medium">Precio contado (Gs.)</label>
    <input type="text" inputmode="numeric" name="price_cash"
           value="{{ old('price_cash', money_py($product->price_cash, false)) }}"
           class="money-py w-full px-3 py-2 rounded bg-gray-800 border border-gray-700"
           placeholder="1.500.000">
  </div>

  {{-- Precios en cuotas dinámicos --}}
  <div>
    <label class="block mb-1 font-medium">Precios en cuotas (opcional)</label>

    <div id="installments-wrapper" class="space-y-2">
      @php $pis = $product->installments ?? collect(); @endphp

      @if(old('installments'))
        @foreach(old('installments') as $i => $cuota)
          <div class="flex gap-2 installment-row">
            <input type="number" min="1" name="installments[{{ $i }}]"
                   value="{{ old('installments.'.$i) }}"
                   placeholder="N° de cuotas"
                   class="w-1/3 px-3 py-2 rounded bg-gray-800 border border-gray-700">
            <input type="text" inputmode="numeric" name="installment_prices[{{ $i }}]"
                   value="{{ old('installment_prices.'.$i) }}"
                   placeholder="Precio por cuota"
                   class="money-py w-2/3 px-3 py-2 rounded bg-gray-800 border border-gray-700">
            <button type="button" class="remove-installment px-2 bg-red-600 text-white rounded">✖</button>
          </div>
        @endforeach
      @elseif($pis->count())
        @foreach($pis as $idx => $inst)
          <div class="flex gap-2 installment-row">
            <input type="number" min="1" name="installments[{{ $idx }}]"
                   value="{{ old('installments.'.$idx, $inst->installments) }}"
                   placeholder="N° de cuotas"
                   class="w-1/3 px-3 py-2 rounded bg-gray-800 border border-gray-700">
            <input type="text" inputmode="numeric" name="installment_prices[{{ $idx }}]"
                   value="{{ old('installment_prices.'.$idx, money_py((int)$inst->installment_price, false)) }}"
                   placeholder="Precio por cuota"
                   class="money-py w-2/3 px-3 py-2 rounded bg-gray-800 border border-gray-700">
            <button type="button" class="remove-installment px-2 bg-red-600 text-white rounded">✖</button>
          </div>
        @endforeach
      @else
        <div class="flex gap-2 installment-row">
          <input type="number" min="1" name="installments[0]"
                 placeholder="N° de cuotas"
                 class="w-1/3 px-3 py-2 rounded bg-gray-800 border border-gray-700">
          <input type="text" inputmode="numeric" name="installment_prices[0]"
                 placeholder="Precio por cuota"
                 class="money-py w-2/3 px-3 py-2 rounded bg-gray-800 border border-gray-700">
          <button type="button" class="remove-installment px-2 bg-red-600 text-white rounded">✖</button>
        </div>
      @endif
    </div>

    <button type="button" id="add-installment"
            class="mt-2 px-3 py-1 bg-purple-600 text-white rounded hover:bg-purple-700">
      ➕ Agregar cuota
    </button>
  </div>

  {{-- 📸 Subir más imágenes (nuevos archivos) --}}
  <div>
    <label class="block mb-1 font-medium">Agregar fotos</label>
    <input type="file" name="images[]" multiple accept="image/*"
           class="w-full px-3 py-2 rounded bg-gray-800 border border-gray-700">
    <p class="text-sm text-gray-400 mt-1">
      Podés subir nuevas. Marcá portada aquí (para <em>nuevos archivos</em>) o abajo en la galería (para <em>existentes</em>). 4MB c/u.
    </p>

    {{-- Índice de portada entre NUEVOS archivos (si se elige) --}}
    <input type="hidden" name="cover_index" id="cover_index" value="">

    {{-- Preview nuevas imágenes con radio de portada --}}
    <div id="preview" class="mt-3 grid grid-cols-2 md:grid-cols-4 gap-3"></div>
  </div>

  {{-- 🖼️ Galería actual: portada (existentes), orden, eliminar --}}
  <div>
    <label class="block mb-2 font-medium">Galería actual</label>
    @if($product->images && $product->images->count())
      <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        @foreach($product->images as $img)
          <div class="relative rounded border border-gray-700 p-2 bg-black/40">
            <img src="{{ asset('storage/'.$img->path) }}" alt="{{ $img->alt }}"
                 class="w-full h-32 object-contain rounded cursor-zoom-in"
                 onclick="openLightbox('{{ asset('storage/'.$img->path) }}')">
            <div class="flex items-center justify-between mt-2 text-xs">
              <label class="flex items-center gap-1">
                <input type="radio" name="cover_id" value="{{ $img->id }}" {{ $img->is_cover ? 'checked' : '' }}>
                Portada
              </label>
              <div class="flex items-center gap-1">
                <span>Orden</span>
                <input type="number" name="orders[{{ $img->id }}]" value="{{ $img->sort_order }}"
                       class="w-14 bg-gray-900 border border-gray-700 rounded px-2 py-1">
              </div>
            </div>

            {{-- ❌ YA NO anidamos formularios aquí.
                 Usamos un botón que dispara un <form> externo mediante "form=" --}}
            <button type="submit"
                    form="img-del-{{ $img->id }}"
                    class="absolute top-2 right-2 px-2 py-1 bg-red-600/80 hover:bg-red-600 text-white rounded"
                    onclick="return confirm('¿Eliminar esta imagen?')">
              ✕
            </button>
          </div>
        @endforeach
      </div>
    @else
      <p class="text-gray-400">Sin imágenes aún.</p>
    @endif
  </div>

  {{-- Stock (solo lectura) --}}
  <div>
    <label class="block mb-1 font-medium">Stock</label>
    <div class="px-3 py-2 rounded bg-gray-800 border border-gray-700 inline-flex items-center gap-2">
      <span class="px-2 py-0.5 rounded text-xs font-semibold
                  {{ $product->stock > 0 ? 'bg-green-600 text-white' : 'bg-gray-500 text-gray-100' }}">
        {{ $product->stock }}
      </span>
      <span class="text-xs text-gray-400">(se actualiza con Compras/Ventas)</span>
    </div>
  </div>

  {{-- Activo --}}
  <div class="flex items-center">
    <input type="hidden" name="active" value="0">
    <input type="checkbox" name="active" id="active" value="1"
           class="w-4 h-4 text-blue-600 border-gray-600 rounded focus:ring-blue-500"
           {{ old('active', $product->active) ? 'checked' : '' }}>
    <label for="active" class="ml-2">Activo</label>
  </div>

  {{-- Notas --}}
  <div>
    <label class="block mb-1 font-medium">Notas</label>
    <textarea name="notes" rows="3"
              class="w-full px-3 py-2 rounded bg-gray-800 border border-gray-700">{{ old('notes',$product->notes) }}</textarea>
  </div>

  {{-- Acciones --}}
  <div class="flex gap-2 mt-4">
    <button class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">Guardar</button>
    <a href="{{ route('products.show',$product) }}"
       class="px-4 py-2 border border-gray-400 text-gray-400 rounded hover:bg-gray-500 hover:text-white">
       Cancelar
    </a>
  </div>
</form>

{{-- ========= FORMS DE ELIMINACIÓN (fuera del form principal) ========= --}}
@if($product->images && $product->images->count())
  @foreach($product->images as $img)
    <form id="img-del-{{ $img->id }}"
          method="POST"
          action="{{ route('products.images.destroy', [$product,$img]) }}"
          class="hidden">
      @csrf
      @method('DELETE')
    </form>
  @endforeach
@endif

{{-- 🔹 Script JS: cuotas dinámicas + máscara de miles + preview imágenes + lightbox --}}
@push('scripts')
<script>
  // Máscara visual de miles
  document.addEventListener('input', function(e){
    if(!e.target.matches('.money-py')) return;
    let raw = e.target.value.replace(/\D+/g,'');
    e.target.value = raw.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  });

  // Agregar filas dinámicas
  document.getElementById('add-installment')?.addEventListener('click', function() {
    const wrapper = document.getElementById('installments-wrapper');
    const idx = wrapper.querySelectorAll('.installment-row').length;
    const div = document.createElement('div');
    div.className = 'flex gap-2 installment-row';
    div.innerHTML = `
      <input type="number" min="1" name="installments[${idx}]" placeholder="N° de cuotas"
             class="w-1/3 px-3 py-2 rounded bg-gray-800 border border-gray-700">
      <input type="text" inputmode="numeric" name="installment_prices[${idx}]" placeholder="Precio por cuota"
             class="money-py w-2/3 px-3 py-2 rounded bg-gray-800 border border-gray-700">
      <button type="button" class="remove-installment px-2 bg-red-600 text-white rounded">✖</button>
    `;
    wrapper.appendChild(div);
  });

  // Remover fila
  document.getElementById('installments-wrapper')?.addEventListener('click', function(e) {
    if (e.target.classList.contains('remove-installment')) {
      e.target.closest('.installment-row').remove();
    }
  });

  // Preview NUEVAS imágenes + radio de portada (cover_index)
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
          cont.className = 'relative rounded border border-gray-700 p-2 block cursor-pointer bg-black/40';
          cont.innerHTML = `
            <img src="${e.target.result}" class="w-full h-32 object-contain rounded">
            <div class="flex items-center justify-between mt-2 text-xs text-gray-300">
              <span class="truncate pr-2">${file.name}</span>
              <span class="inline-flex items-center gap-1">
                <input type="radio" name="cover_index_radio" value="${idx}" ${isDefault ? 'checked' : ''}>
                Portada (nuevo)
              </span>
            </div>
          `;
          preview.appendChild(cont);
          if (isDefault) coverIdx.value = '0';
        };
        reader.readAsDataURL(file);
      });
    });

    // Sincronizar portada elegida: si elige portada nueva, desmarca existente y viceversa
    document.addEventListener('change', (e) => {
      if (e.target && e.target.name === 'cover_index_radio') {
        coverIdx.value = String(e.target.value || '');
        document.querySelectorAll('input[name="cover_id"]').forEach(r => r.checked = false);
      }
      if (e.target && e.target.name === 'cover_id') {
        coverIdx.value = '';
        document.querySelectorAll('input[name="cover_index_radio"]').forEach(r => r.checked = false);
      }
    });
  })();

  // Lightbox simple
  function openLightbox(src) {
    const backdrop = document.createElement('div');
    backdrop.className = 'fixed inset-0 z-50 bg-black/80 flex items-center justify-center p-4';
    backdrop.innerHTML = `
      <div class="relative max-w-5xl w-full">
        <img src="${src}" class="w-full max-h-[85vh] object-contain rounded shadow-2xl">
        <button class="absolute -top-4 -right-4 bg-red-600 hover:bg-red-700 text-white rounded-full p-2">✕</button>
      </div>
    `;
    document.body.appendChild(backdrop);
    const close = () => backdrop.remove();
    backdrop.querySelector('button').addEventListener('click', close);
    backdrop.addEventListener('click', (e) => { if (e.target === backdrop) close(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); }, { once: true });
  }
  window.openLightbox = openLightbox;
</script>
@endpush

@endsection
